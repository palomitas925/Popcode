<?php
$id=$_GET['id']??'';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Etiqueta</title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<style>
body{margin:0;padding:0;text-align:center;}
#etiqueta{width:5cm;height:2.5cm;border:1px solid #000;margin:10px auto;padding:5px;}
</style>
</head>
<body onload="imprimir()">
<div id="etiqueta">
<svg id="barcode"></svg>
<p style="font-size:10px;">ID: <?=htmlspecialchars($id)?></p>
</div>
<script>
function imprimir(){
 JsBarcode("#barcode","<?=$id?>",{format:"CODE128",width:1.5,height:40,displayValue:true});
 window.print();
}
</script>
</body>
</html>
