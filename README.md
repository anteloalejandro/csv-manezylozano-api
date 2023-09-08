# API REST para importar CSVs de tienda.manezylozano.com

*API para el importador de CSVs de Mañez y Lozano escrita usando las librerías de Wordpress y Woocommerce*

## Uso

Poner este directorio con nombre `importar` dentro de la raíz del directorio de wordpress.

## Llamadas

*Exceptuando `/importar/login.php`, es necesario iniciar sesión en `/wp-login.php` para utilizar cualquiera de las llamadas.*

### **POST** `/importar/piezas/`

Recibe un sólo archivo CSV de un formulario con `enctype=multipart/formdata`. El campo que contenga el archivo ha de llamarse `piezas-csv`.

Devuelve un objeto CsvImportResponse convertido en JSON, con los siguientes atributos:

```json
{
	"error": false,
	"error_msg": "",
	"warnings": [
		{
			"dataType": "part",
			"ref": "none",
			"message": "Error de formato en la fila 1"
		}
	]
}
```

### **POST** `/importar/despieces/`

Recibe un sólo archivo CSV de un formulario con `enctype=multipart/formdata`. El campo que contenga el archivo ha de llamarse `despieces-csv`.

Devuelve un objeto CsvImportResponse convertido en JSON, con los siguientes atributos:

```json
{
	"error": false,
	"error_msg": "",
	"warnings": [
		{
			"dataType": "assembly",
			"ref": "SKU_INVENTADO",
			"message": "Pieza SKU_INVENTADO no encontrada"
		}
	]
}
```

### **GET** `/importar/login.php`

Indica si el usuario ha iniciado sesión como administrador o no.

```json
{
	"error": true,
	"message": "No se ha podido iniciar sesión",
	"redirect_link": "http://localhost:8801/wp-login.php?redirect_to=http%3A%2F%2Flocalhost%3A8801%2Fimportar%2Fcsv&reauth=1"
}
```

> `redirect_link` contiene un enlace para redirigir a `/wp-login.php` y volver a la aplicación en caso de que no se haya iniciado sesión

## Formato del archivo CSV

Los archivos CSV han de estar separados por comas y envueltos en comillas dobles. La primera línea siempre será ignorada, ya que se asume que es ahí donde está la cabecera de la tabla. Ej.:
```
Artículo,Descripción,P.V.P.,Ubicación
100803,MODULO GRIFOS C/PALANCAS M-2020,"49,5",
101201,"JUNTA METAL-BUNA BSP 1/4""","0,18",4-183-1
```

## Importador

El código del importador que utiliza está api se encuentra [aquí](https://github.com/anteloalejandro/csv-manezylozano-api).
