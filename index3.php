<?php 
error_reporting(E_ALL & ~(E_STRICT|E_NOTICE));
session_start();

$cid = $_SESSION['_user_id'];//ecriptar jwt
if(!$cid){
    $cid = md5(time()); 
    $_SESSION['_user_id'] = $cid;
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Game</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
		<link type="text/css" rel="stylesheet" href="main.css">
		<style>
			body {
				background-color: #fff;
				color: #444;
				margin: 0;
			}
			a {
				color: #08f;
			}
			#control{
				position: absolute;
				max-height: 100%;
				overflow: scroll;
				background-color: #00000059;
				padding: 15px;
				color: white;
				text-transform: capitalize;
			}
		</style>
	</head>

	<body>
		<div id="control">
			
		</div>
		<!-- Import maps polyfill -->
		<!-- Remove this when import maps will be widely supported -->
		<script async src="./otros/es-module-shims.js"></script>

		<script type="importmap">
			{
				"imports": {
					"three": "./vendor/mrdoob/three.js/build/three.module.js"
				}
			}
		</script>

		<script type="module">

			var cid = "<?php echo($cid); ?>";
			import * as THREE from 'three';

			// import Stats from './vendor/mrdoob/three.js/examples/jsm/libs/stats.module.js';

			import { OrbitControls } from './vendor/mrdoob/three.js/examples/jsm/controls/OrbitControls.js';
			
			import { GLTFLoader } from './vendor/mrdoob/three.js/examples/jsm/loaders/GLTFLoader.js';
			import { GLTFExporter } from './vendor/mrdoob/three.js/examples/jsm/exporters/GLTFExporter.js';
			import * as SkeletonUtils from './vendor/mrdoob/three.js/examples/jsm/utils/SkeletonUtils.js';

			// import {_ws} from './otros/events.js'

			let SCREEN_WIDTH = window.innerWidth;
			let SCREEN_HEIGHT = window.innerHeight;

			let container, stats;
			let camera, scene, renderer;

			let cameraControls;
			var character;
			
			var mixers;
			var light,gltfMain;
			const loader = new GLTFLoader();
			var faceExpres,faceValue;
			var avatar,head, body, pelo;
			var colorPiel = {r:.420,g:.272,b:.179}
			var colorPelo = {r:.0,g:.272,b:.0}
			init();
			animate();

			function init() {

				container = document.createElement( 'div' );
				document.body.appendChild( container );

				// CAMERA				
				camera = new THREE.PerspectiveCamera( 45, window.innerWidth / window.innerHeight, 1, 1000 );
				camera.position.set( 0, 1,45 );
				// camera.lookAt( 0, 10, 0 );
				// SCENE
				scene = new THREE.Scene();
				scene.background = new THREE.Color( 0xffffff );
				// scene.fog = new THREE.Fog( 0xffffff, 1000, 4000 );
				scene.add( camera );

				// LIGHTS
				scene.add( new THREE.AmbientLight( 0x222222 ) );

				light = new THREE.DirectionalLight( 0xffffff, 2.25 );
				light.position.set( 200, 450, 500 );

				light.castShadow = true;

				light.shadow.mapSize.width = 1024;
				light.shadow.mapSize.height = 512;

				light.shadow.camera.near = 100;
				light.shadow.camera.far = 1200;

				light.shadow.camera.left = - 1000;
				light.shadow.camera.right = 1000;
				light.shadow.camera.top = 350;
				light.shadow.camera.bottom = - 350;

				scene.add( light );
				// scene.add( new THREE.CameraHelper( light.shadow.camera ) );


				const dirLight = new THREE.DirectionalLight( 0xffffff );
				dirLight.position.set( - 3, 10, - 10 );
				dirLight.castShadow = true;
				dirLight.shadow.camera.top = 4;
				dirLight.shadow.camera.bottom = - 4;
				dirLight.shadow.camera.left = - 4;
				dirLight.shadow.camera.right = 4;
				dirLight.shadow.camera.near = 0.1;
				dirLight.shadow.camera.far = 40;
				scene.add( dirLight );

				// RENDERER
				renderer = new THREE.WebGLRenderer( { antialias: true } );
				renderer.setPixelRatio( window.devicePixelRatio );
				renderer.setSize( SCREEN_WIDTH, SCREEN_HEIGHT );
				
				container.appendChild( renderer.domElement );

				renderer.outputEncoding = THREE.sRGBEncoding;
				renderer.shadowMap.enabled = true;
				
				// CONTROLS
				cameraControls = new OrbitControls( camera, renderer.domElement );
				cameraControls.target.x = 0;
				cameraControls.target.y = 1.5;
				cameraControls.target.z = 0;
				cameraControls.maxDistance = 5.00;
				cameraControls.minDistance = 2;
				// cameraControls.maxPolarAngle = Math.PI * 0.45;
				// cameraControls.minPolarAngle = 0;
				// cameraControls.rotateSpeed = 0.4;
				// cameraControls.minAzimuthAngle = Math.PI *0.5;
				// cameraControls.maxAzimuthAngle = Math.PI *0.5;
				cameraControls.update();

				// initAvatar2();
				initFace();
				initEvents();
				// initBoxes();
			}

			function initFace(){
				var mylist = document.getElementById('control');
				var face = 0;
				var all = {}
				// loader.load( './vendor/mrdoob/three.js/examples/models/gltf/Soldier.glb', function ( gltf ) {
				loader.load( './models/glb/head.glb', function ( gltf ) {
				// loader.load( './models/glb/Runningx24.glb', function ( gltf ) {

					gltf.scene.traverse( function ( object ) {

						if ( object.isMesh) {
							switch(object.name){
								case 'Wolf3D_Head_1':{
									var texture = new THREE.TextureLoader().load("./models/glb/face_mask_AO_female.jpg");
									texture.flipY = false;
									texture.encoding = THREE.sRGBEncoding;
									object.material.map = texture;
									object.material.color.r = colorPiel.r;
									object.material.color.g = colorPiel.g;
									object.material.color.b = colorPiel.b;
									
									if (object.morphTargetDictionary){
										faceExpres = Object.keys(object.morphTargetDictionary);
										faceValue =  object.morphTargetInfluences;
										
										object.morphTargetInfluences[object.morphTargetDictionary['AAA_neck']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['Disney_neck_thin']] = 0.3;
										object.morphTargetInfluences[object.morphTargetDictionary['faceShape10']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['faceShape08']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['Disney_nose_small']] = 0.7;
										object.morphTargetInfluences[object.morphTargetDictionary['lipShape01']] = 0.7;
										object.morphTargetInfluences[object.morphTargetDictionary['shape_022']] = 0.3;
										object.morphTargetInfluences[object.morphTargetDictionary['FRT_nose_2']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['FRT_eyebrows']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['FRT_eyes']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['FRT_cheeks_2']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['AAA_chin']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['eyeShape06']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['eyeShape10']] = 1;
										object.morphTargetInfluences[object.morphTargetDictionary['noseShape02']] = 0.81;
										object.morphTargetInfluences[object.morphTargetDictionary['lipShape07']] = 1;
										// console.log(object.morphTargetInfluences);
										// console.log(object);
										for(var i =0; i<faceExpres.length;i++){
											all [faceExpres[i]] = faceValue[i];
											var el = faceExpres[i]+':<br> <input type="range" id="'+faceExpres[i]+'" data-index='+i+' data-face='+face+' max=1 min=0 value='+faceValue[i]+' step=0.01><br>';
											mylist.insertAdjacentHTML('beforeend', el);
											document.getElementById(faceExpres[i]).addEventListener("input",(e)=>{
												e = e.srcElement;
												head.children[0].children[e.dataset.face].morphTargetInfluences[e.dataset.index] = e.value;
											})
										}
										face++;							
									} 
									break;
								}
								case 'Wolf3D_Head_2':{
									var texture = new THREE.TextureLoader().load("./models/glb/1626452485-eye-12-mask.jpg");
									texture.flipY = false;
									texture.encoding = THREE.sRGBEncoding;
									object.material.map = texture;
									break;
								}
							}
							// console.log(object.material);							
						}
					} );
					// gltfMain = gltf;
					head = gltf.scene;//SkeletonUtils.clone( gltf.scene );
					// mixers = new THREE.AnimationMixer( head );
					head.position.x = -0.015;
					head.position.z = -0.001;
					head.position.y = 1.435;

					// head.scale.multiplyScalar(9);//(100,100,100);
					// scene.add( head );
					initBody();
				} );
			}

			function initBody(){
				var face = 0;
				var all = {}
				// loader.load( './vendor/mrdoob/three.js/examples/models/gltf/Soldier.glb', function ( gltf ) {
				loader.load( './models/glb/1648628681-outfit-rogue-01-v2-f.glb', function ( gltf ) {
				// loader.load( './models/glb/Runningx24.glb', function ( gltf ) {

					gltf.scene.traverse( function ( object ) {

						if ( object.isMesh) {
							if (object.type == 'SkinnedMesh' && object.name=='Wolf3D_Body') {
								// let bones = SkeletonUtils.getBones(object.skeleton);
								// skeleton = new THREE.Skeleton(bones);
							}
							
							switch(object.name){
								case 'Wolf3D_Head_1':{
									var texture = new THREE.TextureLoader().load("./models/glb/face_mask_AO_female.jpg");
									texture.flipY = false;
									texture.encoding = THREE.sRGBEncoding;
									object.material.map = texture;
									if (object.morphTargetDictionary){
										faceExpres = Object.keys(object.morphTargetDictionary);
										faceValue =  object.morphTargetInfluences;
										
										// object.morphTargetInfluences[11] = 1;
										// console.log(object.morphTargetInfluences);
										// console.log(object);
										for(var i =0; i<faceExpres.length;i++){
											all [faceExpres[i]] = faceValue[i];
											var el = faceExpres[i]+':<br> <input type="range" id="'+faceExpres[i]+'" data-index='+i+' data-face='+face+' max=1 min=0 value='+faceValue[i]+' step=0.01><br>';
											mylist.insertAdjacentHTML('beforeend', el);
											document.getElementById(faceExpres[i]).addEventListener("input",(e)=>{
												e = e.srcElement;
												mixers._root.children[0].children[e.dataset.face].morphTargetInfluences[e.dataset.index] = e.value;
											})
										}
										face++;							
									} 
									break;
								}
								case 'Wolf3D_Body':{
									object.material.color.r = colorPiel.r;
									object.material.color.g = colorPiel.g;
									object.material.color.b = colorPiel.b;
									break;
								}
							}						
						}
					} );
					body = gltf.scene;					
					body.position.x = 0;
					body.position.z = 0;
					body.position.y = 0;

					// body.scale.multiplyScalar(10);

					avatar = new THREE.Group();
					avatar.add( head );
					avatar.add( body );
					initPose();
				} );
			}

			function initPose(){
				var face = 0;
				var all = {}
				// loader.load( './vendor/mrdoob/three.js/examples/models/gltf/Soldier.glb', function ( gltf ) {
				loader.load( './models/glb/FemalePose.glb', function ( gltf ) {
				// loader.load( './models/glb/Runningx24.glb', function ( gltf ) {

					gltf.scene.traverse( function ( object ) {
						var b = SkeletonUtils.getBoneByName(object.name,body.children[0].children[1].skeleton);
						if (b) {
							b.position.copy(object.position);
							b.rotation.copy(object.rotation);
							b.scale.copy(object.scale);
						}
					} );					
				} );
				initPelo();
			}			

			function initPelo(){
				var face = 0;
				var all = {}
				// loader.load( './vendor/mrdoob/three.js/examples/models/gltf/Soldier.glb', function ( gltf ) {
				loader.load( './models/glb/1627915229-hair-19.glb', function ( gltf ) {
				// loader.load( './models/glb/Runningx24.glb', function ( gltf ) {					
					pelo = gltf.scene;
					pelo.position.x = head.position.x;
					pelo.position.y = head.position.y;
					pelo.position.z = head.position.z;

					pelo.children[0].material.color.r = colorPelo.r;
					pelo.children[0].material.color.g = colorPelo.g;
					pelo.children[0].material.color.b = colorPelo.b;
					// console.log(pelo)
					// pelo.scale.multiplyScalar(9);
					avatar.add(pelo)
					scene.add( avatar );
					console.log(avatar)
					// saveFile();
				} );				
			}

			function saveFile(){				
				const exporter = new GLTFExporter();
				exporter.parse(
					avatar,
					function (result) {
						saveArrayBuffer(result, 'scene.gltf');
					},
					{ binary: true,	trs:true, includeCustomExtensions: true,forceIndices:1 }
				);
			}

			function saveArrayBuffer(buffer, filename) {
				save(new Blob([buffer], { type: 'application/octet-stream' }), filename);
			}

			function save(blob, filename) {
				const link = document.createElement('a');
				link.style.display = 'none';
				document.body.appendChild(link); // Firefox workaround, see #6594
				link.href = URL.createObjectURL(blob);
				link.download = filename;
				link.click();

				// URL.revokeObjectURL( url ); breaks Firefox...
			}

			function initEvents(){
				window.addEventListener( 'resize', onWindowResize );
			}

			function onWindowResize() {

				SCREEN_WIDTH = window.innerWidth;
				SCREEN_HEIGHT = window.innerHeight;

				// posX0 = window.innerHeight/2;
				// posY0 = window.innerWidth/2;

				renderer.setSize( SCREEN_WIDTH, SCREEN_HEIGHT );

				camera.aspect = SCREEN_WIDTH / SCREEN_HEIGHT;
				camera.updateProjectionMatrix();

			}

			
			function animate() {
				requestAnimationFrame( animate );
				
				render();
			
				renderer.render( scene, camera );

			}

			function render() {

			}

		</script>

	</body>
</html>