#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Componente de inferencia: cuenta personas con YOLO y refina las detecciones con SAM 3.

El conteo y las cajas son siempre las de YOLO. SAM 3 recibe esas cajas como indicaciones y
devuelve una mascara por persona; con ellas se mide que tan consistente es cada deteccion
(mascara debil, personas encimadas) y se dibuja una sola imagen con ambos resultados.
Si SAM 3 falla o se pasa del tiempo, el resultado de YOLO se conserva y se informa el motivo.

Contrato de intercambio :
  - Solicitud: un archivo JSON con los identificadores, la ruta de la imagen, los pesos
    y los parametros.
  - Respuesta: un archivo JSON con el estado, el conteo y las detecciones, o un error
    controlado. Nunca devuelve un conteo ficticio cuando algo falla.

Este script no usa credenciales ni accede a la base de datos.

Uso:
  python contar_personas.py --solicitud solicitud.json --respuesta respuesta.json
  python contar_personas.py --info --pesos modelos/yolov8s.pt --respuesta info.json
"""
import argparse
import hashlib
import json
import os
import multiprocessing
import platform
import queue
import sys
import time
from pathlib import Path


def milisegundos(inicio):
    return int((time.perf_counter() - inicio) * 1000)


def escribir_json(ruta, datos):
    """Escribe de forma atomica: Laravel nunca lee un archivo a medias."""
    destino = Path(ruta)
    destino.parent.mkdir(parents=True, exist_ok=True)
    temporal = destino.with_name(destino.name + '.tmp')

    with open(temporal, 'w', encoding='utf-8') as archivo:
        json.dump(datos, archivo, ensure_ascii=False)

    os.replace(temporal, destino)


def huella_sha256(ruta):
    huella = hashlib.sha256()

    with open(ruta, 'rb') as archivo:
        for bloque in iter(lambda: archivo.read(1024 * 1024), b''):
            huella.update(bloque)

    return huella.hexdigest()


def huella_cacheada(ruta):
    """SHA-256 de un archivo grande (SAM 3 pesa varios GB). Se guarda junto al archivo y solo se
    recalcula si cambian su tamano o su fecha de modificacion."""
    ruta = Path(ruta)
    estado = ruta.stat()
    cache = ruta.with_name(ruta.name + '.huella.json')

    try:
        guardado = json.loads(cache.read_text(encoding='utf-8'))

        if guardado.get('bytes') == estado.st_size and guardado.get('mtime_ns') == estado.st_mtime_ns:
            return guardado['sha256']
    except Exception:
        pass

    huella = huella_sha256(ruta)

    try:
        cache.write_text(json.dumps({'bytes': estado.st_size, 'mtime_ns': estado.st_mtime_ns, 'sha256': huella}), encoding='utf-8')
    except Exception:
        pass

    return huella


def poligono_de(mascara, maximo_puntos=80):
    """Contorno exterior mas grande de una mascara booleana, simplificado para guardarlo en JSON."""
    import cv2
    import numpy as np

    contornos, _ = cv2.findContours(mascara.astype(np.uint8), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    if not contornos:
        return []

    contorno = max(contornos, key=cv2.contourArea)
    epsilon = 0.002 * cv2.arcLength(contorno, True)
    simple = cv2.approxPolyDP(contorno, epsilon, True)

    while len(simple) > maximo_puntos:
        epsilon *= 1.5
        simple = cv2.approxPolyDP(contorno, epsilon, True)

    return [[int(x), int(y)] for x, y in simple.reshape(-1, 2)]


def calcular_refinamiento(mascaras, cajas, relacion_minima, solape_maximo):
    """Mide la consistencia de cada deteccion con su mascara de SAM 3.

    - relacion_mascara_caja: area de la mascara / area de la caja (mascara muy chica = poco confiable)
    - solape_max: mayor interseccion con otra mascara, entre el area de la mas pequena de las dos
      (si se encimaron, a veces dos cajas son la misma persona o una tapa a otra)
    Devuelve una lista por deteccion (sin poligono)."""
    areas = [int(m.sum()) for m in mascaras]
    solapes = [0.0] * len(mascaras)

    for i in range(len(mascaras)):
        for j in range(i + 1, len(mascaras)):
            xi1, yi1, xi2, yi2 = [int(v) for v in cajas[i]]
            xj1, yj1, xj2, yj2 = [int(v) for v in cajas[j]]
            x1, y1, x2, y2 = max(xi1, xj1), max(yi1, yj1), min(xi2, xj2), min(yi2, yj2)

            if x2 <= x1 or y2 <= y1 or min(areas[i], areas[j]) == 0:
                continue

            comun = int((mascaras[i][y1:y2, x1:x2] & mascaras[j][y1:y2, x1:x2]).sum())
            coeficiente = comun / min(areas[i], areas[j])
            solapes[i] = max(solapes[i], coeficiente)
            solapes[j] = max(solapes[j], coeficiente)

    resultado = []

    for indice, caja in enumerate(cajas):
        area_caja = max(1.0, (caja[2] - caja[0]) * (caja[3] - caja[1]))
        relacion = areas[indice] / area_caja
        motivos = []

        if areas[indice] == 0:
            motivos.append('sin_mascara')
        elif relacion < relacion_minima:
            motivos.append('mascara_debil')

        if solapes[indice] > solape_maximo:
            motivos.append('solapada')

        resultado.append({
            'estado': 'revisar' if motivos else 'consistente',
            'motivos': motivos,
            'area_mascara': areas[indice],
            'relacion_mascara_caja': round(float(relacion), 4),
            'solape_max': round(float(solapes[indice]), 4),
        })

    return resultado


def dibujar_resultado(lienzo, detecciones, sam):
    """Una sola imagen: mascaras de SAM 3 (si hay), cajas de YOLO y numero de cada persona.
    Verde = consistente, naranja = conviene revisar."""
    import cv2
    import numpy as np

    VERDE, NARANJA = (0, 200, 0), (0, 140, 255)
    paleta = [(255, 120, 0), (0, 200, 255), (200, 0, 200), (0, 220, 120), (255, 60, 120), (120, 120, 255)]

    relleno = lienzo.copy()

    for i, d in enumerate(detecciones):
        poligono = (d.get('refinamiento') or {}).get('poligono') or []

        if len(poligono) >= 3:
            cv2.fillPoly(relleno, [np.array(poligono, dtype=np.int32)], paleta[i % len(paleta)])

    lienzo = cv2.addWeighted(relleno, 0.4, lienzo, 0.6, 0)

    for i, d in enumerate(detecciones):
        ref = d.get('refinamiento') or {}
        color = NARANJA if ref.get('estado') == 'revisar' else VERDE
        poligono = ref.get('poligono') or []

        if len(poligono) >= 3:
            cv2.polylines(lienzo, [np.array(poligono, dtype=np.int32)], True, color, 2, cv2.LINE_AA)

        x1, y1, x2, y2 = [int(v) for v in d['caja']]
        cv2.rectangle(lienzo, (x1, y1), (x2, y2), color, 2)
        cv2.putText(
            lienzo, '#%d %.2f' % (i + 1, d['confianza']), (x1, max(y1 - 6, 12)),
            cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 1, cv2.LINE_AA
        )

    if sam and sam.get('estado') == 'aplicado':
        texto = 'YOLO: %d personas | SAM 3: %d a revisar' % (len(detecciones), sam.get('a_revisar', 0))
    elif sam:
        texto = 'YOLO: %d personas | SAM 3 no disponible' % len(detecciones)
    else:
        texto = 'YOLO: %d personas' % len(detecciones)

    cv2.rectangle(lienzo, (0, 0), (8 + 9 * len(texto), 24), (0, 0, 0), -1)
    cv2.putText(lienzo, texto, (6, 17), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1, cv2.LINE_AA)

    return lienzo


def _trabajo_sam(salida, pesos, ruta_imagen, cajas, dispositivo, relacion_minima, solape_maximo):
    """Se ejecuta en un proceso aparte para poder cortarlo si se pasa del tiempo."""
    try:
        inicio = time.perf_counter()
        import cv2
        import numpy as np
        import ultralytics
        from ultralytics import SAM

        imagen = cv2.imread(ruta_imagen)

        if imagen is None:
            salida.put({'ok': False, 'codigo': 'IMAGEN_NO_LEGIBLE', 'mensaje': 'SAM 3 no pudo leer la imagen'})
            return

        alto, ancho = imagen.shape[:2]
        modelo = SAM(pesos)
        opciones = {'bboxes': [list(map(float, c)) for c in cajas], 'verbose': False}

        if dispositivo:
            opciones['device'] = dispositivo

        resultado = modelo(imagen, **opciones)[0]

        if resultado.masks is None or len(resultado.masks.data) != len(cajas):
            recibidas = 0 if resultado.masks is None else len(resultado.masks.data)
            salida.put({
                'ok': False, 'codigo': 'MASCARAS_INCOMPLETAS',
                'mensaje': 'SAM 3 devolvio %d mascaras para %d cajas' % (recibidas, len(cajas)),
            })
            return

        datos = resultado.masks.data.cpu().numpy()
        mascaras = []

        for m in datos:
            m = m > 0.5

            if m.shape != (alto, ancho):
                m = cv2.resize(m.astype(np.uint8), (ancho, alto), interpolation=cv2.INTER_NEAREST).astype(bool)

            mascaras.append(m)

        refinamientos = calcular_refinamiento(mascaras, cajas, relacion_minima, solape_maximo)

        for ref, mascara in zip(refinamientos, mascaras):
            ref['poligono'] = poligono_de(mascara)

        salida.put({
            'ok': True,
            'version': 'ultralytics ' + ultralytics.__version__,
            'refinamientos': refinamientos,
            'duracion_ms': milisegundos(inicio),
        })
    except MemoryError:
        salida.put({'ok': False, 'codigo': 'MEMORIA_INSUFICIENTE', 'mensaje': 'No hay memoria suficiente para SAM 3'})
    except Exception as error:
        salida.put({'ok': False, 'codigo': 'ERROR_SAM', 'mensaje': '%s: %s' % (type(error).__name__, error)})


def refinar_con_sam(config_sam, ruta_imagen, cajas, dispositivo, trabajo=_trabajo_sam):
    """Segunda etapa. Nunca lanza: siempre devuelve (informacion_sam, refinamientos o None)."""
    inicio = time.perf_counter()
    pesos = config_sam.get('pesos')
    base = {'estado': 'omitido', 'pesos': Path(pesos).name if pesos else None, 'version': None, 'sha256': None}

    if not cajas:
        return {**base, 'mensaje': 'No hubo detecciones que refinar', 'duracion_ms': 0}, None

    try:
        base['sha256'] = huella_cacheada(pesos)
        relacion_minima = float(config_sam.get('relacion_minima', 0.25))
        solape_maximo = float(config_sam.get('solape_maximo', 0.30))
        limite = float(config_sam.get('timeout_s', 600))
    except Exception as error:
        return {**base, 'estado': 'error', 'codigo': 'SAM_CONFIG_INVALIDA', 'mensaje': str(error)[:300], 'duracion_ms': milisegundos(inicio)}, None

    contexto = multiprocessing.get_context('spawn')
    salida = contexto.Queue()
    proceso = contexto.Process(
        target=trabajo,
        args=(salida, pesos, ruta_imagen, cajas, dispositivo, relacion_minima, solape_maximo),
        daemon=True,
    )
    proceso.start()

    mensaje = None
    fin = time.monotonic() + limite

    while time.monotonic() < fin:
        try:
            mensaje = salida.get(timeout=1)
            break
        except queue.Empty:
            if not proceso.is_alive():
                try:
                    mensaje = salida.get(timeout=2)
                except queue.Empty:
                    pass

                break

    proceso.join(5)

    if proceso.is_alive():
        proceso.kill()
        proceso.join(5)

    duracion = milisegundos(inicio)

    if mensaje is None:
        if time.monotonic() >= fin:
            return {**base, 'estado': 'error', 'codigo': 'SAM_TIEMPO_EXCEDIDO',
                    'mensaje': 'SAM 3 supero el limite de %d segundos' % limite, 'duracion_ms': duracion}, None

        return {**base, 'estado': 'error', 'codigo': 'SAM_SIN_RESPUESTA',
                'mensaje': 'El proceso de SAM 3 termino sin resultado', 'duracion_ms': duracion}, None

    if not mensaje.get('ok'):
        return {**base, 'estado': 'error', 'codigo': mensaje.get('codigo', 'ERROR_SAM'),
                'mensaje': str(mensaje.get('mensaje', ''))[:300], 'duracion_ms': duracion}, None

    refinamientos = mensaje['refinamientos']
    a_revisar = sum(1 for r in refinamientos if r['estado'] == 'revisar')

    return {
        **base,
        'estado': 'aplicado',
        'version': mensaje.get('version'),
        'duracion_ms': duracion,
        'consistentes': len(refinamientos) - a_revisar,
        'a_revisar': a_revisar,
    }, refinamientos


def entorno_efectivo():
    """Versiones y equipo con los que realmente se ejecuto."""
    info = {
        'python': platform.python_version(),
        'sistema': platform.platform(),
        'procesador': platform.processor() or platform.machine(),
        'ultralytics': None,
        'torch': None,
        'cuda': False,
        'gpu': None,
    }

    try:
        import ultralytics
        info['ultralytics'] = ultralytics.__version__
    except Exception:
        pass

    try:
        import torch
        info['torch'] = torch.__version__
        info['cuda'] = bool(torch.cuda.is_available())

        if info['cuda']:
            info['gpu'] = torch.cuda.get_device_name(0)
    except Exception:
        pass

    return info


def respuesta_error(ids, codigo, mensaje, inicio):
    return {
        **ids,
        'estado': 'error',
        'codigo': codigo,
        'mensaje': str(mensaje)[:500],
        'duracion_ms': milisegundos(inicio),
    }


def modo_info(args, inicio):
    """Informa versiones y huella de los pesos para registrar una configuracion."""
    if not args.pesos or not Path(args.pesos).is_file():
        return respuesta_error({}, 'PESOS_NO_ENCONTRADOS', 'No se encontro el archivo de pesos indicado', inicio)

    entorno = entorno_efectivo()

    if not entorno['ultralytics']:
        return respuesta_error({}, 'DEPENDENCIAS_FALTANTES', 'No se pudo importar ultralytics', inicio)

    info = {
        'estado': 'ok',
        'yolo_version': 'ultralytics ' + entorno['ultralytics'],
        'pesos': Path(args.pesos).name,
        'sha256': huella_sha256(args.pesos),
        'entorno': entorno,
    }

    if args.pesos_sam:
        if not Path(args.pesos_sam).is_file():
            return respuesta_error({}, 'PESOS_SAM_NO_ENCONTRADOS', 'No se encontro el archivo de pesos de SAM 3', inicio)

        try:
            from ultralytics import SAM  # noqa: F401
        except Exception as error:
            return respuesta_error({}, 'DEPENDENCIAS_FALTANTES', 'No se pudo importar SAM de ultralytics: %s' % error, inicio)

        info['sam'] = {
            'version': 'ultralytics ' + entorno['ultralytics'] + ' / SAM 3',
            'pesos': Path(args.pesos_sam).name,
            'sha256': huella_cacheada(args.pesos_sam),
        }

    return info


def leer_solicitud(ruta):
    with open(ruta, 'r', encoding='utf-8') as archivo:
        return json.load(archivo)


def modo_inferencia(args, inicio):
    ids = {'procesamiento_id': None, 'evidencia_id': None, 'configuracion_id': None}

    # 1) Solicitud legible y completa
    try:
        solicitud = leer_solicitud(args.solicitud)
        ids = {
            'procesamiento_id': solicitud['procesamiento_id'],
            'evidencia_id': solicitud['evidencia_id'],
            'configuracion_id': solicitud['configuracion_id'],
        }
        ruta_imagen = solicitud['ruta_imagen']
        pesos = solicitud['pesos']
        directorio = Path(solicitud['directorio_resultados'])
        parametros = solicitud['parametros']
        clase_id = int(parametros['clase_id'])
        etiqueta = str(parametros['etiqueta'])
        conf = float(parametros['conf'])
        iou = float(parametros['iou'])
        imgsz = int(parametros['imgsz'])
        max_det = int(parametros.get('max_det', 300))
        dispositivo = parametros.get('dispositivo') or None
        config_sam = solicitud.get('sam') or None

        if config_sam is not None and not Path(str(config_sam.get('pesos', ''))).is_file():
            return respuesta_error(ids, 'PESOS_SAM_NO_ENCONTRADOS', 'No se encontro el archivo de pesos de SAM 3', inicio)
    except Exception as error:
        return respuesta_error(ids, 'SOLICITUD_INVALIDA', 'La solicitud no es valida: %s' % error, inicio)

    if not (0 < conf <= 1 and 0 < iou <= 1 and 32 <= imgsz <= 4096 and 1 <= max_det <= 1000 and clase_id >= 0):
        return respuesta_error(ids, 'PARAMETROS_INVALIDOS', 'Los parametros estan fuera de rango', inicio)

    # 2) Archivos necesarios
    if not Path(ruta_imagen).is_file():
        return respuesta_error(ids, 'IMAGEN_NO_ENCONTRADA', 'No se encontro la imagen a procesar', inicio)

    if not Path(pesos).is_file():
        return respuesta_error(ids, 'PESOS_NO_ENCONTRADOS', 'No se encontro el archivo de pesos', inicio)

    if not directorio.is_dir():
        return respuesta_error(ids, 'DIRECTORIO_INVALIDO', 'El directorio de resultados no existe', inicio)

    # 3) Dependencias
    try:
        from ultralytics import YOLO
        import cv2
    except Exception as error:
        return respuesta_error(ids, 'DEPENDENCIAS_FALTANTES', 'Falta una dependencia: %s' % error, inicio)

    # 4) Inferencia
    try:
        modelo = YOLO(pesos)

        if modelo.names.get(clase_id) != etiqueta:
            return respuesta_error(
                ids, 'CLASE_NO_DISPONIBLE',
                'El modelo no tiene la clase %s con el identificador %d' % (etiqueta, clase_id), inicio
            )

        opciones = dict(
            source=ruta_imagen, conf=conf, iou=iou, imgsz=imgsz,
            classes=[clase_id], max_det=max_det, verbose=False,
        )

        if dispositivo:
            opciones['device'] = dispositivo

        inicio_inferencia = time.perf_counter()
        resultado = modelo.predict(**opciones)[0]
        inferencia_ms = milisegundos(inicio_inferencia)

        cajas = resultado.boxes.xyxy.cpu().tolist()
        confianzas = resultado.boxes.conf.cpu().tolist()
        clases = resultado.boxes.cls.cpu().tolist()

        # Solo se conservan las detecciones de la clase persona
        detecciones = []

        for caja, confianza, clase in zip(cajas, confianzas, clases):
            if int(clase) != clase_id:
                continue

            detecciones.append({
                'caja': [round(float(valor), 1) for valor in caja],
                'confianza': round(float(confianza), 4),
            })

        alto, ancho = resultado.orig_shape[0], resultado.orig_shape[1]

        # Segunda etapa: SAM 3 refina las cajas de YOLO. El conteo no cambia.
        sam = None

        if config_sam is not None:
            sam, refinamientos = refinar_con_sam(config_sam, ruta_imagen, [d['caja'] for d in detecciones], dispositivo)

            if refinamientos:
                for deteccion, refinamiento in zip(detecciones, refinamientos):
                    deteccion['refinamiento'] = refinamiento

        # Una sola imagen con el resultado de ambos modelos, para que el profesor la revise
        anotada = None

        try:
            lienzo = dibujar_resultado(resultado.orig_img.copy(), detecciones, sam)

            if cv2.imwrite(str(directorio / 'anotada.jpg'), lienzo, [cv2.IMWRITE_JPEG_QUALITY, 85]):
                anotada = 'anotada.jpg'
        except Exception:
            anotada = None

        return {
            **ids,
            'estado': 'procesado',
            'clase': {'id': clase_id, 'etiqueta': etiqueta},
            'conteo': len(detecciones),
            'detecciones': detecciones,
            'imagen': {'ancho': int(ancho), 'alto': int(alto)},
            'modelo': {
                'pesos': Path(pesos).name,
                'sha256': huella_sha256(pesos),
            },
            'parametros': {'conf': conf, 'iou': iou, 'imgsz': imgsz, 'max_det': max_det},
            'anotada': anotada,
            'sam': sam,
            'inferencia_ms': inferencia_ms,
            'duracion_ms': milisegundos(inicio),
            'entorno': entorno_efectivo(),
        }
    except MemoryError:
        return respuesta_error(ids, 'MEMORIA_INSUFICIENTE', 'No hay memoria suficiente para procesar la imagen', inicio)
    except Exception as error:
        return respuesta_error(ids, 'ERROR_EJECUCION', '%s: %s' % (type(error).__name__, error), inicio)


def main():
    inicio = time.perf_counter()

    analizador = argparse.ArgumentParser(description='Conteo de personas con YOLO')
    analizador.add_argument('--solicitud', help='Archivo JSON con la solicitud')
    analizador.add_argument('--respuesta', required=True, help='Archivo JSON donde se escribe la respuesta')
    analizador.add_argument('--info', action='store_true', help='Informa versiones y huella de los pesos')
    analizador.add_argument('--pesos', help='Pesos a inspeccionar (solo con --info)')
    analizador.add_argument('--pesos-sam', dest='pesos_sam', help='Pesos de SAM 3 a inspeccionar (solo con --info)')
    args = analizador.parse_args()

    if args.info:
        respuesta = modo_info(args, inicio)
    elif args.solicitud:
        respuesta = modo_inferencia(args, inicio)
    else:
        respuesta = respuesta_error({}, 'USO_INCORRECTO', 'Indica --solicitud o --info', inicio)

    try:
        escribir_json(args.respuesta, respuesta)
    except Exception as error:
        sys.stderr.write('No se pudo escribir la respuesta: %s\n' % error)
        return 2

    return 0


if __name__ == '__main__':
    sys.exit(main())
