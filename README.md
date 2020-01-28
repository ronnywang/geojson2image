## GeoJSON to Image

This is a PHP class for generate Image from GeoJSON.

### How to use

```php
include('./GeoJSON2Image.php');
$max_size = 1000;
$json = file_get_contents('./TaiwanCounty.json');

$ret = new GeoJSON2Image($json, $max_size);

$ret->image = './outputImage.png';   // GD Image object
$ret->boundry = array(100, 120, 100, 120); // array(min_x, max_x, min_y, max_y) boundry
```

### Files
* GeoJSON2Image.php: main class
* geojson2png: generate png from geojson file
* TaiwanCounty.json: Taiwan County GeoJSON sample
* TaiwanCounty.png: Taiwan County output (command: ./geojson2png TaiwanCounty.json; mv output.png TaiwanCounty.png )
