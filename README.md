## GeoJSON to Image

This is a PHP class for generate Image from GeoJSON.

### How to use

```php
include('./geojson2image/GeoJSON2Image.php');
$max_size = 1000;
$json = json_decode(file_get_contents('./geojson2image/TaiwanCounty.json'));

$ret = new GeoJSON2Image($json, $max_size);

$ret->boundry = array(100, 120, 100, 120); // array(min_x, max_x, min_y, max_y) boundry
$ret->draw('./image.png');   // GD Image object
```

### Files
* GeoJSON2Image.php: main class
* geojson2png: generate png from geojson file
* TaiwanCounty.json: Taiwan County GeoJSON sample
* TaiwanCounty.png: Taiwan County output (command: ./geojson2png TaiwanCounty.json; mv output.png TaiwanCounty.png )
