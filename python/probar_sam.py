#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Prueba rapida de SAM 3 con las cajas de YOLO, antes de usarlo en el sistema.

Uso (desde la carpeta del proyecto):
  python\\.venv\\Scripts\\python.exe python\\probar_sam.py --imagen ruta\\foto.jpg
Genera prueba_sam.jpg junto a esta imagen y muestra cuanto tarda cada etapa.
"""
import argparse
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))


def main():
    analizador = argparse.ArgumentParser()
    analizador.add_argument('--imagen', required=True)
    analizador.add_argument('--yolo', default='python/modelos/yolov8s.pt')
    analizador.add_argument('--sam', default='python/modelos/sam3.pt')
    analizador.add_argument('--timeout', type=int, default=900)
    args = analizador.parse_args()

    import cv2
    import contar_personas as cp
    from ultralytics import YOLO

    t0 = time.perf_counter()
    resultado = YOLO(args.yolo).predict(source=args.imagen, conf=0.25, iou=0.7, imgsz=640, classes=[0], verbose=False)[0]
    detecciones = [
        {'caja': [round(float(v), 1) for v in caja], 'confianza': round(float(conf), 4)}
        for caja, conf in zip(resultado.boxes.xyxy.cpu().tolist(), resultado.boxes.conf.cpu().tolist())
    ]
    print('YOLO: %d personas en %.1f s' % (len(detecciones), time.perf_counter() - t0))

    t1 = time.perf_counter()
    sam, refinamientos = cp.refinar_con_sam(
        {'pesos': args.sam, 'timeout_s': args.timeout}, args.imagen, [d['caja'] for d in detecciones], None
    )
    print('SAM 3: %s en %.1f s' % (sam['estado'], time.perf_counter() - t1))

    if sam['estado'] != 'aplicado':
        print('Detalle:', sam.get('codigo'), sam.get('mensaje'))
        return 1

    for d, r in zip(detecciones, refinamientos):
        d['refinamiento'] = r

    for i, r in enumerate(refinamientos, 1):
        print('  #%d %s relacion=%.2f solape=%.2f %s' % (i, r['estado'], r['relacion_mascara_caja'], r['solape_max'], ','.join(r['motivos'])))

    salida = Path(args.imagen).with_name('prueba_sam.jpg')
    cv2.imwrite(str(salida), cp.dibujar_resultado(resultado.orig_img.copy(), detecciones, sam))
    print('Imagen guardada en', salida)
    return 0


if __name__ == '__main__':
    sys.exit(main())
