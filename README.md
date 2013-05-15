## GeoJSON to Image

This is a PHP class for generate Image from GeoJSON.

### How to use

```php
include(__DIR__ . '/GeoJSON2Image.php');
$max_size = 1000;
$ret = GeoJSON2Image::json2image($json, $max_size);
$ret->image;   // GD Image object
$ret->boundry; // array(min_x, max_x, min_y, max_y) boundry
```

### Files
* GeoJSON2Image.php: main class
* geojson2png: generate png from geojson file
* TaiwanCounty.json: Taiwan County GeoJSON sample
* TaiwanCounty.png: Taiwan County output (command: ./geojson2png TaiwanCounty.json; mv output.png TaiwanCounty.png )
