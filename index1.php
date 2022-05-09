<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Oreintation</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
		<link type="text/css" rel="stylesheet" href="./otros/main.css">
		<link type="text/css" rel="stylesheet" href="./otros/style.css">
	</head>

	<body>
		
		<!-- Import maps polyfill -->
		<!-- Remove this when import maps will be widely supported -->
		<script async src="./otros/es-module-shims.js"></script>


		<div id="canvas_container" class="position:absolute"></canvas></div>

		<div style="position: fixed; bottom:1px; right: 95px;">
			<button id="btnAction">Text</button>
		</div>
		<script type="importmap">
			{
				"imports": {
					"three": "./vendor/mrdoob/three.js/build/three.module.js"
				}
			}
		</script>
		<!-- <script src="./otros/three.min.js" wfd-invisible="true"></script> -->
		<script src="./otros/simplex-noise.min.js" wfd-invisible="true"></script>
		
		<script type="module">
			import { camera,controls } from "./js/main.js"
			// console.log(camera);
			let btnAction = ()=>{
				controls.mouseStatus = 0;
				controls.object.position.set(0,0,2);
				controls.tmpQuaternion.set( 0,0,0, 1 ).normalize();
				controls.object.quaternion._x = 0;
				controls.object.quaternion._y = 0;
				controls.object.quaternion._z = 0;
				controls.object.quaternion._w = 1;
			}

			document.getElementById("btnAction").addEventListener('click',btnAction);
			
		</script>

	</body>
</html>