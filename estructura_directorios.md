# Estructura de directorios del módulo Informes Contables

```
/modules/informescontables/
│
├── informescontables.php (archivo principal)
├── config.xml
├── logo.png (32x32px)
├── index.php
│
├── /controllers/
│   ├── index.php
│   └── /admin/
│       ├── index.php
│       └── AdminInformesContablesController.php
│
├── /views/
│   ├── index.php
│   ├── /css/
│   │   ├── index.php
│   │   └── back.css
│   ├── /js/
│   │   ├── index.php
│   │   └── back.js
│   ├── /img/
│   │   ├── index.php
│   │   └── chosen-sprite.png (necesario para el plugin Chosen)
│   └── /templates/
│       ├── index.php
│       └── /admin/
│           ├── index.php
│           └── informes.tpl
│
├── /translations/
│   ├── index.php
│   └── es.php
│
├── /libraries/
│   ├── index.php
│   └── PHPExcel.php (debes descargar PHPExcel 1.8.2)
│
└── /exports/
    └── index.php (directorio para guardar archivos generados)
```

## Notas importantes:

1. **PHPExcel**: Debes descargar PHPExcel 1.8.2 desde [GitHub](https://github.com/PHPOffice/PHPExcel) y colocar los archivos en `/libraries/`

2. **Permisos**: El directorio `/exports/` debe tener permisos de escritura (755 o 777)

3. **index.php**: Cada directorio debe contener el archivo index.php de seguridad que te proporcioné

4. **chosen-sprite.png**: Puedes obtenerlo del plugin Chosen o usar la versión incluida en PrestaShop

## Instalación:

1. Crea la estructura de directorios
2. Copia todos los archivos en sus ubicaciones correspondientes
3. Descarga e instala PHPExcel en `/libraries/`
4. Crea el logo.png de 32x32px
5. Comprime todo en un ZIP
6. Sube e instala desde el backoffice de PrestaShop

## Después de instalar:

1. Ve a Módulos > Módulos y Servicios
2. Busca "Informes Contables"
3. Haz clic en "Configurar"
4. Ajusta el importe mínimo del Modelo 347 si es necesario
5. El módulo aparecerá en Pedidos > Informes Contables