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
		</style>
	</head>

	<body>

		<!-- Import maps polyfill -->
		<!-- Remove this when import maps will be widely supported -->
		<script async src="./otros/es-module-shims.js"></script>
		<script src="./vendor/mrdoob/three.js/examples/js/libs/ammo.wasm.js"></script>
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
			import { Gyroscope } from './vendor/mrdoob/three.js/examples/jsm/misc/Gyroscope.js';
			import { MD2CharacterComplex } from './vendor/mrdoob/three.js/examples/jsm/misc/MD2CharacterComplex.js';

			
			import { GLTFLoader } from './vendor/mrdoob/three.js/examples/jsm/loaders/GLTFLoader.js';
			import * as SkeletonUtils from './vendor/mrdoob/three.js/examples/jsm/utils/SkeletonUtils.js';

			import { FBXLoader } from './vendor/mrdoob/three.js/examples/jsm/loaders/FBXLoader.js';
			import { OBJLoader } from './vendor/mrdoob/three.js/examples/jsm/loaders/OBJLoader.js';
			import { MTLLoader } from './vendor/mrdoob/three.js/examples/jsm/loaders/MTLLoader.js';

			// import {_ws} from './otros/events.js'

			let SCREEN_WIDTH = window.innerWidth;
			let SCREEN_HEIGHT = window.innerHeight;

			let container, stats;
			let camera, scene, renderer;

			const characters = [];
			let nCharacters = 0;

			let cameraControls;
			var character;

			const controlsAll = {

				moveForward: false,
				moveBackward: false,
				moveLeft: false,
				moveRight: false

			};
			
			const controlsOgro = {

				moveForward: false,
				moveBackward: false,
				moveLeft: false,
				moveRight: false

			};

			var worldBox = new THREE.Box3();
			var characterPos;
			var gyro;
			const clock = new THREE.Clock();
			var mapSize = 16000;

			var ground;
			var light;


			// let camera, scene, renderer, controls;

			const objects = [];

			let raycaster;

			let moveForward = false;
			let moveBackward = false;
			let moveLeft = false;
			let moveRight = false;
			let canJump = false;

			let prevTime = performance.now();
			const velocity = new THREE.Vector3();
			const direction = new THREE.Vector3();
			const vertex = new THREE.Vector3();
			const color = new THREE.Color();

			let characterBox = new THREE.Box3();
			var characterLastPosition = new THREE.Vector3();
			var goinBack = false;

			
			var playerCollider;

			//SOLDIERS
			var mixers;
			var userList = [];

			var auxWs = false;
			const loader = new GLTFLoader();
			var gltfMain;

			// Heightfield parameters
			const terrainWidthExtents = mapSize;
			const terrainDepthExtents = mapSize;
			const terrainWidth = 256;
			const terrainDepth = 256;
			const terrainHalfWidth = terrainWidth / 2;
			const terrainHalfDepth = terrainDepth / 2;
			const terrainMaxHeight = 80;
			const terrainMinHeight = -20;
			let terrainMesh;

			// Physics variables
			let collisionConfiguration;
			let dispatcher;
			let broadphase;
			let solver;
			let physicsWorld;
			const dynamicObjects = [];
			let transformAux1;

			let heightData = null;
			let ammoHeightData = null;

			var opObject;
			var scale = {x:0,y:0,z:0};
			var avatarScale = 30;
			const STATE = { DISABLE_DEACTIVATION : 4 }
			const FLAGS = { CF_KINEMATIC_OBJECT: 2 }
			
			var ghostObject, ammoTmpPos,tmpTrans,avatarControl;
			Ammo().then( function ( AmmoLib ) {
				Ammo = AmmoLib;
				
				init();
				animate();
			});

			function connectService(){
				var host = 'ws://127.0.0.1:12345/ws.php';
				var _ws = new WebSocket(host);

				_ws.onclose = ()=>{
					console.log("Connection is closed...");
					connectService();
				}

				_ws.onmessage = function (evt) { 
					var msg = JSON.parse(evt.data);

					if(msg){
						switch(msg['op']){
							case 'up':{
								var id = msg['uid'];

								var el = userList[id];
								if(!el){
									userList[id] = "w";
									addUser(msg);								
									wait(msg);								
								}else{
									moveUser(msg);
								}
								
								break;
							}
							case 'disc':{
								var id = msg['uid'];
								console.log(msg)
								if(userList[id]){
									// console.log(msg)
									removeObject3D(userList[id]._root);
								}
								break;
							}
						}
					}
					// console.log(msg);
				};

				_ws.onopen = function(e) {
					var delta = clock.getDelta();
					updateMovementModel( delta,mixers,controlsAll )
					sendPosition(delta);
				};

				auxWs = _ws;
			}

			function init() {
				heightData = generateHeight( terrainWidth, terrainDepth, terrainMinHeight, terrainMaxHeight );
				ammoTmpPos = new Ammo.btVector3();
				tmpTrans = new Ammo.btTransform();
   	 			// ammoTmpRot = new Ammo.btVector3();
				initScene();
				initControl();
											
				initPhysics();
				loadMap();
				initAvatar1();
				initEvents();
				// initGround()
				// initBoxes();
			}

			function generateHeight( width, depth, minHeight, maxHeight ) {

				// Generates the height data (a sinus wave)

				const size = width * depth;
				const data = new Float32Array( size );

				const hRange = maxHeight - minHeight;
				const w2 = width / 2;
				const d2 = depth / 2;
				const phaseMult = 12;

				let p = 0;

				for ( let j = 0; j < depth; j ++ ) {

					for ( let i = 0; i < width; i ++ ) {

						const radius = Math.sqrt(
							Math.pow( ( i - w2 ) / w2, 2.0 ) +
								Math.pow( ( j - d2 ) / d2, 2.0 ) );

						const height = ( Math.sin( radius * phaseMult ) + 1 ) * 0.5 * hRange + minHeight;

						data[ p ] = height;

						p ++;

					}

				}

				return data;

			}

			function initPhysics() {

				// Physics configuration
				collisionConfiguration = new Ammo.btDefaultCollisionConfiguration();
				dispatcher = new Ammo.btCollisionDispatcher( collisionConfiguration );
				broadphase = new Ammo.btDbvtBroadphase();
				solver = new Ammo.btSequentialImpulseConstraintSolver();
				physicsWorld = new Ammo.btDiscreteDynamicsWorld( dispatcher, broadphase, solver, collisionConfiguration );
				physicsWorld.setGravity( new Ammo.btVector3( 0, -9.8, 0 ) );

				transformAux1 = new Ammo.btTransform();
			}

			function createTerrainShape() {
				// This parameter is not really used, since we are using PHY_FLOAT height data type and hence it is ignored
				const heightScale = 1;
				// Up axis = 0 for X, 1 for Y, 2 for Z. Normally 1 = Y is used.
				const upAxis = 1;
				// hdt, height data type. "PHY_FLOAT" is used. Possible values are "PHY_FLOAT", "PHY_UCHAR", "PHY_SHORT"
				const hdt = 'PHY_FLOAT';
				// Set this to your needs (inverts the triangles)
				const flipQuadEdges = false;
				// Creates height data buffer in Ammo heap
				ammoHeightData = Ammo._malloc( 4 * terrainWidth * terrainDepth );
				// Copy the javascript height data array to the Ammo one.
				let p = 0;
				let p2 = 0;
				for ( let j = 0; j < terrainDepth; j ++ ) {
					for ( let i = 0; i < terrainWidth; i ++ ) {
						// write 32-bit float data to memory
						Ammo.HEAPF32[ ammoHeightData + p2 >> 2 ] = heightData[ p ];
						p ++;
						// 4 bytes/float
						p2 += 4;
					}
				}

				// Creates the heightfield physics shape
				const heightFieldShape = new Ammo.btHeightfieldTerrainShape(
					terrainWidth,
					terrainDepth,
					ammoHeightData,
					heightScale,
					terrainMinHeight,
					terrainMaxHeight,
					upAxis,
					hdt,
					flipQuadEdges
				);

				// Set horizontal scale
				const scaleX = terrainWidthExtents / ( terrainWidth - 1 );
				const scaleZ = terrainDepthExtents / ( terrainDepth - 1 );
				heightFieldShape.setLocalScaling( new Ammo.btVector3( 100, 1, 100 ) );

				heightFieldShape.setMargin( 0.05 );
				return heightFieldShape;
			}

			function initScene(){
				container = document.createElement( 'div' );
				document.body.appendChild( container );
				// CAMERA				
				camera = new THREE.PerspectiveCamera( 45, window.innerWidth / window.innerHeight, 1, 1000 );
				camera.position.set( 0, 5.50, 13.00 );
				// camera.position.set( 2, 3, - 6 );
				// camera.lookAt( 0, 1, 0 );

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

				raycaster = new THREE.Raycaster();
				
				// RENDERER
				renderer = new THREE.WebGLRenderer( { antialias: true } );
				renderer.setPixelRatio( window.devicePixelRatio );
				renderer.setSize( SCREEN_WIDTH, SCREEN_HEIGHT );
				// renderer.sortObjects = true;
				// renderer.physicallyCorrectLights = true;
				container.appendChild( renderer.domElement );

				renderer.outputEncoding = THREE.sRGBEncoding;
				renderer.shadowMap.enabled = true;
				// renderer.shadowMap.type = THREE.PCFSoftShadowMap;
			}

			function initControl(){
				// CONTROLS
				cameraControls = new OrbitControls( camera, renderer.domElement );
				// cameraControls.maxDistance = 8.00;
				// cameraControls.minDistance = 4.50;
				// cameraControls.maxPolarAngle = Math.PI * 0.45;
				// cameraControls.minPolarAngle = 0;
				// // cameraControls.rotateSpeed = 0.4;
				// cameraControls.minAzimuthAngle = Math.PI *0.5;
				// cameraControls.maxAzimuthAngle = Math.PI *0.5;
				cameraControls.update();
			}

			function loadMap(){
				var OBJFile = './models/obj/terrain.obj';
				var MTLFile = './models/obj/terrain.mtl';
				var JPGFile = './models/obj/terrain.png';

				new MTLLoader()
					.load(MTLFile, function (materials) {
						materials.preload();
						new OBJLoader()
							.setMaterials(materials)
							.load(OBJFile, function (object) {
								const groundMaterial = new THREE.MeshPhongMaterial( { color: 0xC7C7C7 } );
								groundMaterial.side = THREE.DoubleSide;

								terrainMesh = new THREE.Mesh( object.children[0].geometry, groundMaterial );
								terrainMesh.receiveShadow = true;
								terrainMesh.castShadow = true;
								terrainMesh.scale.multiplyScalar(100)
								terrainMesh.position.y = -6.1;
								const textureLoader = new THREE.TextureLoader();
								textureLoader.load( './vendor/mrdoob/three.js/examples/textures/terrain/grasslight-big.jpg', function ( texture ) {
									texture.wrapS = THREE.RepeatWrapping;
									texture.wrapT = THREE.RepeatWrapping;
									texture.repeat.set( terrainWidth - 1, terrainDepth - 1 );
									groundMaterial.map = texture;
									groundMaterial.needsUpdate = true;
								} );

								scene.add( terrainMesh );

								// add plane to phys
								addPlane(terrainMesh);
							});
					});
			}

			function addPlane(object){
				const transform = new Ammo.btTransform();
				transform.setIdentity();
				transform.setOrigin(new Ammo.btVector3(0, 0, 0));
				transform.setRotation(new Ammo.btQuaternion(0, 0, 0, 1));
				const motionState = new Ammo.btDefaultMotionState(transform);

				// retrieve vertices positions from object
				console.log(object)
				const vertices = object.geometry.attributes.uv;
				const indices = object.geometry.attributes.position;
				// const scale = [0.15933185815811157, 1.1706310510635376, 0.15933185815811157];
				let verticesPos = object.geometry.attributes.position;
				let triangles = [];

				const mesh = new Ammo.btTriangleMesh(true, true);
				mesh.setScaling(new Ammo.btVector3(100, 100, 100));

				for (let i = 0; i * 3 < indices.count; i++) {
					mesh.addTriangle(
						new Ammo.btVector3(vertices[indices[i * 3] * 3], vertices[indices[i * 3] * 3 + 1], vertices[indices[i * 3] * 3 + 2]),
						new Ammo.btVector3(vertices[indices[i * 3 + 1] * 3], vertices[indices[i * 3 + 1] * 3 + 1], vertices[indices[i * 3 + 1] * 3 + 2]),
						new Ammo.btVector3(vertices[indices[i * 3 + 2] * 3], vertices[indices[i * 3 + 2] * 3 + 1], vertices[indices[i * 3 + 2] * 3 + 2]),
						false
					);
				}
				
				console.log("done");
				const shape = new Ammo.btBvhTriangleMeshShape(mesh, true, true);

				const localInertia = new Ammo.btVector3(0, 0, 0);

				const rbInfo = new Ammo.btRigidBodyConstructionInfo(0, motionState, shape, localInertia);
				const body = new Ammo.btRigidBody(rbInfo);

				physicsWorld.addRigidBody(body);
			}

			function updatePlayer( deltaTime ) {
				
				playerCollider.set(mixers._root.position,playerCollider.radius);

				// raycaster.setFromCamera( character.root.position, camera );

				raycaster.ray.origin.copy(mixers._root.position);

				var dir = new THREE.Vector3();
				
				mixers._root.getWorldDirection(dir);
				// dir.multiplyScalar(100);
				// if(goinBack){
				dir.multiplyScalar(-1);
				// }
				raycaster.ray.direction.copy(dir);
				raycaster.far = 200;
				
				const intersects = raycaster.intersectObjects(objects);
				
				if(!intersects.length){
					characterLastPosition.copy(mixers._root.position);
				}else{
					// console.log(intersects)
					mixers._root.position.copy(characterLastPosition);
				}
				if(mixers._root.userData.speed>0){
					sendPosition(deltaTime);
				}
			}
			
			function wait(msg){
				setTimeout(()=>{
					if(userList[msg['uid']] == "w")
						wait(msg);
					else{
						moveUser(msg);
					}
				},1000)
			}

			function moveUser(msg){
				var el = userList[msg['uid']];
				if(el!="w"){
					el._root.position.x = msg['x'];
					el._root.position.y = msg['y'];
					el._root.position.z = msg['z'];
					// const delta = clock.getDelta();	
					updateMovementModel(msg['delta'],el,msg['control']);
				}
			}

			const sendPosition = (delta)=>{
				if(auxWs.readyState!=1){
					return;
				}
				var mouseP = {
					x:mixers._root.position.x,
					y:mixers._root.position.y,
					z:mixers._root.position.z,
					dir:mixers._root.rotation.y,
					uid:cid,
					op:'up',
					control:controlsAll,
					delta:delta
				};
				auxWs.send(JSON.stringify(mouseP));
				// console.log(mouseP)
			}

			//events

			function initBoxes(){
				const boxGeometry = new THREE.BoxGeometry( 20, 20, 20 ).toNonIndexed();
				let position = boxGeometry.attributes.position;
				const colorsBox = [];

				for ( let i = 0, l = position.count; i < l; i ++ ) {

					color.setHSL( Math.random() * 0.3 + 0.5, 0.75, Math.random() * 0.25 + 0.75 );
					colorsBox.push( color.r, color.g, color.b );

				}

				boxGeometry.setAttribute( 'color', new THREE.Float32BufferAttribute( colorsBox, 3 ) );

				for ( let i = 0; i < 500; i ++ ) {

					const boxMaterial = new THREE.MeshPhongMaterial( { specular: 0xffffff, flatShading: true, vertexColors: true } );
					boxMaterial.color.setHSL( Math.random() * 0.2 + 0.5, 0.75, Math.random() * 0.25 + 0.75 );

					const box = new THREE.Mesh( boxGeometry, boxMaterial );
					box.position.x = Math.floor( Math.random() * 20 - 10 ) * 100;
					box.position.y = 10;
					box.position.z = Math.floor( Math.random() * 20 - 10) * 100;

					box.castShadow = true;
					box.receiveShadow = true;

					scene.add( box );
					objects.push(box);
				}
			}

			function removeObject3D(object3D) {
				if (!(object3D instanceof THREE.Object3D)) return false;

				if(object3D instanceof THREE.Mesh)
					object3D.geometry.dispose();

				if(object3D.material){
					if (object3D.material instanceof Array) {
						object3D.material.forEach(material => material.dispose());
					} else {
						object3D.material.dispose();
					}
				}
				var p = object3D.name;
				object3D.removeFromParent();
				
				userList = userList.filter(function(value, index, arr){ 
					return value != p;
				});
				return true;
			}

			function addUser(msg){
				if(userList[msg['uid']]!="w"){
					return;
				}
				// const loader = new GLTFLoader();
				//aqui se debe pasar que elemento va a cargar
				loader.load( './vendor/mrdoob/three.js/examples/models/gltf/Soldier.glb', function ( gltf ) {

					gltf.scene.traverse( function ( object ) {

						if ( object.isMesh ) {
							object.castShadow = true;
						}

					} );
					
					const model1 = SkeletonUtils.clone( gltf.scene );

					var mixersAux = new THREE.AnimationMixer( model1 );
					var anima1 = mixersAux.clipAction( gltf.animations[0]).play();
					var anima2 = mixersAux.clipAction( gltf.animations[1]);
					model1.position.x = msg['x'];
					model1.position.y = msg['y'];
					model1.position.z = msg['z'];
					model1.rotation.y = msg['dir'];

					// model1.rotation.y = -3;
					model1.userData.animationFPS = 6;
					model1.userData.transitionFrames = 15;

					// movement model parameters
					model1.userData.maxSpeed = 105;
					model1.userData.maxReverseSpeed = - 105;
					model1.userData.frontAcceleration = 600;
					model1.userData.backAcceleration = 600;
					model1.userData.frontDecceleration = 600;
					model1.userData.angularSpeed = 2.5;
					model1.userData.speed = 0;
					model1.userData.bodyOrientation = model1.rotation.y;
					model1.userData.walkSpeed = model1.userData.maxSpeed;
					model1.userData.crouchSpeed = 175;
					model1.userData.animations = [anima1,anima2];

					model1.scale.multiplyScalar(avatarScale);//(100,100,100);

					model1.name = msg['uid'];
					
					userList[msg['uid']] = mixersAux;
					scene.add( model1 );
					
					
					// playerCollider = new THREE.Sphere(mixers._root.position, 50 );
					// characterLastPosition.copy(mixers._root.position);
				} );
			}

			function initAvatar2(){
				const loader = new FBXLoader();
				loader.load( './models/glb/untitled1.fbx', function ( object ) {
					// console.log(object)
					mixers = new THREE.AnimationMixer( object );
					
					const action = mixers.clipAction( object.animations[ 0 ] );
					action.play();

					var anima2 = object.animations[ 0 ];

					object.traverse( function ( child ) {
						if ( child.isMesh ) {
							child.castShadow = true;
							child.receiveShadow = true;
						}
					} );

					// gyro = new Gyroscope();
					// gyro.add( camera );
					// gyro.add( light, light.target );
					// object.add( gyro );
					// object.scale.set(.01, .01, .01);
					// console.log(object);
					mixers._root = object;
					scene.add( object );

					function initAvatar3(anima2){
						const loader = new FBXLoader();
						loader.load( './models/glb/08b9a614-37fa-4d72-a2b2-55f8d4385fae.fbx', function ( object ) {
						// console.log(object)
						var mixer1 = new THREE.AnimationMixer( object );
						const action = mixer1.clipAction( anima2 );
						action.play();

						// mixers._root.userData.animations.anima2 = object.animations[ 0 ];

						object.traverse( function ( child ) {
							if ( child.isMesh ) {
								child.castShadow = true;
								child.receiveShadow = true;
							}
						} );
						
						scene.add( object );

					} );
					}
				} );				
			}

			function initAvatar1(){
				loader.load( 'https://d1a370nemizbjq.cloudfront.net/c4630e2f-fa54-4e8a-9537-0aab3e45b76f.glb', function ( gltf ) {
					gltf.scene.traverse( function ( object ) {
						if ( object.isMesh ) {
							object.castShadow = true;
							// console.log(object)
							if (object.morphTargetDictionary){
								// console.log("sss")
								object.morphTargetDictionary.mouthSmile = 0;
							} 
						}
					} );

					loader.load( './models/glb/animation.gltf', function ( gltf1 ) {
						gltfMain = gltf1;
						const model1 = SkeletonUtils.clone( gltf.scene );
						// return initAvatar();
						mixers = new THREE.AnimationMixer( model1 );

						// mixers._actions = gltf.animations;
						// mixers.clipAction( mixers._actions[0]).play(); // idle
						var anima1 = mixers.clipAction( gltfMain.animations[0]).play();
						var anima2 = mixers.clipAction( gltfMain.animations[2]);
						var anima3 = mixers.clipAction( gltfMain.animations[1]);
						model1.position.x = 10;
						model1.position.z = 50;
						model1.position.y = 5;

						// model1.rotation.y = -3;
						model1.userData.animationFPS = 160;
						model1.userData.transitionFrames = 150;

						// movement model parameters
						model1.userData.maxSpeed = 250;
						model1.userData.maxReverseSpeed = - 105;
						model1.userData.frontAcceleration = 600;
						model1.userData.backAcceleration = 600;
						model1.userData.frontDecceleration = 600;
						model1.userData.angularSpeed = 2.5;
						model1.userData.speed = 0;
						model1.userData.bodyOrientation = model1.rotation.y;
						model1.userData.walkSpeed = 50;
						model1.userData.crouchSpeed = model1.userData.maxSpeed;
						model1.userData.animations = [anima1,anima2,anima3];
						// model1.userData.animations = [anima1,anima3];

						model1.scale.multiplyScalar(avatarScale);//(100,100,100);
						scene.add( model1 );
						
						gyro = new Gyroscope();
						gyro.add( camera );
						gyro.add( light, light.target );
						model1.add( gyro );

						var radius = 50;
						var height = 50;
						playerCollider = new THREE.Sphere(mixers._root.position, radius );
						characterLastPosition.copy(mixers._root.position);

						addElement(model1);
					});			

					// initAvatar();
					// connectService();
				} );
			}

			function addPlayer(threeObject){
				const mass = 1;

				scale = {x:threeObject.scale.x-10, y:threeObject.scale.y+avatarScale, z:threeObject.scale.z-10}
				let boxGeometry = new THREE.BoxBufferGeometry(scale.x,scale.y,scale.z);
				let edges = new THREE.EdgesGeometry(boxGeometry);
				opObject = new THREE.LineSegments( edges, new THREE.LineBasicMaterial({ color: 0x0000ff }));

				opObject.position.set(threeObject.position.x, threeObject.position.y+avatarScale, threeObject.position.z);
				var quat = new THREE.Quaternion()
				threeObject.getWorldQuaternion( quat ); 
				scene.add(opObject);

				let transform = new Ammo.btTransform();
				transform.setIdentity();
				transform.setOrigin( new Ammo.btVector3( opObject.position.x, opObject.position.y, opObject.position.z ) );
    			transform.setRotation( new Ammo.btQuaternion( quat.x, quat.y, quat.z, quat.w ) );

				let motionState = new Ammo.btDefaultMotionState( transform );
				let colShape = new Ammo.btBoxShape( new Ammo.btVector3( scale.x * 0.5, scale.y * 0.5, scale.z * 0.5 ) );
    			colShape.setMargin( 0.05 );
				// const shape = new Ammo.btCapsuleShape(0.5, 0.5)

				const ghostObject = new Ammo.btPairCachingGhostObject()
				
				ghostObject.setWorldTransform(transform)
				ghostObject.setCollisionShape(colShape)
				ghostObject.setCollisionFlags(ghostObject.getCollisionFlags() | 16) //CHARACTER_OBJECT

				const controller = new Ammo.btKinematicCharacterController(ghostObject, colShape, 0.35, 1)
				controller.setUseGhostSweepTest(true)

				controller.setGravity(- 9.8);
				// it falls through the ground if I apply gravity
				// controller.setGravity(-this.physicsWorld.getGravity().y())

				// move slowly to the right
				controller.setWalkDirection(new Ammo.btVector3(0.05, 0, 0))

				// addCollisionObject(collisionObject: Ammo.btCollisionObject, collisionFilterGroup?: number | undefined, collisionFilterMask?: number | undefined): void
				physicsWorld.addCollisionObject(ghostObject)
				physicsWorld.addAction(controller)

				avatarControl = controller;
				// let localInertia = new Ammo.btVector3( 0, 0, 0 );
    			// colShape.calculateLocalInertia( mass, localInertia );
				// let rbInfo = new Ammo.btRigidBodyConstructionInfo( mass, motionState, colShape, localInertia );
    			// let body = new Ammo.btRigidBody( rbInfo );
				// threeObject.userData.physicsBody = body;
				// dynamicObjects.push(threeObject);
			}
			
			function addElement(threeObject){
				const mass = 1;

				scale = {x:threeObject.scale.x-10, y:threeObject.scale.y+avatarScale, z:threeObject.scale.z-10}
				let boxGeometry = new THREE.BoxBufferGeometry(scale.x,scale.y,scale.z);
				let edges = new THREE.EdgesGeometry(boxGeometry);
				opObject = new THREE.LineSegments( edges, new THREE.LineBasicMaterial({ color: 0x0000ff }));

				opObject.position.set(threeObject.position.x, threeObject.position.y+avatarScale, threeObject.position.z);
				var quat = new THREE.Quaternion()
				threeObject.getWorldQuaternion( quat ); 
				scene.add(opObject);

				let transform = new Ammo.btTransform();
				transform.setIdentity();
				transform.setOrigin( new Ammo.btVector3( opObject.position.x, opObject.position.y, opObject.position.z ) );
    			transform.setRotation( new Ammo.btQuaternion( quat.x, quat.y, quat.z, quat.w ) );

				let motionState = new Ammo.btDefaultMotionState( transform );
				let colShape = new Ammo.btBoxShape( new Ammo.btVector3( scale.x * 0.5, scale.y * 0.5, scale.z * 0.5 ) );
    			colShape.setMargin( 0.05 );

				let localInertia = new Ammo.btVector3( 0, 0, 0 );
    			colShape.calculateLocalInertia( mass, localInertia );

				let rbInfo = new Ammo.btRigidBodyConstructionInfo( mass, motionState, colShape, localInertia );
    			let body = new Ammo.btRigidBody( rbInfo );
				// body.setFriction(4);
				// body.setActivationState( 4 );
				body.setActivationState( STATE.DISABLE_DEACTIVATION );
				body.setCollisionFlags( FLAGS.CF_KINEMATIC_OBJECT );

				physicsWorld.addRigidBody( body );

				threeObject.userData.physicsBody = body;
				dynamicObjects.push(threeObject);
			}

			function initAvatar(){
				const texture = new THREE.TextureLoader().load("./models/glb/Wolf3D_Avatar_Diffuse.png");
				loader.load( './models/glb/modelo_solo.gltf', function ( gltf ) {
				// loader.load( './models/glb/Runningx24.glb', function ( gltf ) {

					gltf.scene.traverse( function ( object ) {

						if ( object.isMesh ) {
							object.castShadow = true;
							object.material.metalness = 0;
							object.material.vertexColors = false;
							texture.flipY = false;
							object.material.map = texture;

							if (object.morphTargetDictionary){
								object.morphTargetDictionary.mouthSmile = 0;
								// console.log(object.morphTargetDictionary);
								// object.morphTargetDictionary.
							} 
						}

					} );
				gltf.animations = gltfMain.animations;
				const model1 = SkeletonUtils.clone( gltf.scene );
				console.log(gltf)
				mixers = new THREE.AnimationMixer( model1 );
				mixers._root.animations = gltfMain.animations;
				// mixers._actions = gltf.animations;
				// mixers.clipAction( mixers._actions[0]).play(); // idle
				var anima1 = mixers.clipAction( gltfMain.animations[0]).play();
				// var anima2 = mixers.clipAction( gltfMain.animations[2]);
				var anima3 = mixers.clipAction( gltfMain.animations[1]);
				model1.position.x = 100;
				model1.position.z = 50;

				// console.log(model1,"2");
				// model1.rotation.y = -3;
				model1.userData.animationFPS = 6;
				model1.userData.transitionFrames = 15;

				// movement model parameters
				model1.userData.maxSpeed = 105;
				model1.userData.maxReverseSpeed = - 105;
				model1.userData.frontAcceleration = 600;
				model1.userData.backAcceleration = 600;
				model1.userData.frontDecceleration = 600;
				model1.userData.angularSpeed = 2.5;
				model1.userData.speed = 0;
				model1.userData.bodyOrientation = model1.rotation.y;
				model1.userData.walkSpeed = model1.userData.maxSpeed;
				model1.userData.crouchSpeed = 175;
				// model1.userData.animations = [anima1,anima2,anima3];
				model1.userData.animations = [anima1,anima3];

				// console.log(mixers)
				// mixers = mixers1;

				model1.scale.multiplyScalar(avatarScale);//(100,100,100);
				scene.add( model1 );
				
				gyro = new Gyroscope();
				gyro.add( camera );
				gyro.add( light, light.target );
				model1.add( gyro );
			});
				// console.log(anima1.isRunning());
				// mixers._activateAction(1)
				// animate();
				// playerCollider = new THREE.Sphere(mixers._root.position, 50 );
				// characterLastPosition.copy(mixers._root.position);

				// initAvatar();
				// connectService();
				

				// const configOgro = {

				// baseUrl: './vendor/mrdoob/three.js/examples/models/md2/ogro/',

				// body: 'ogro.md2',
				// skins: [ 'grok.jpg', 'ogrobase.png', 'arboshak.png', 'ctf_r.png', 'ctf_b.png', 'darkam.png', 'freedom.png',
				// 		'gib.png', 'gordogh.png', 'igdosh.png', 'khorne.png', 'nabogro.png',
				// 		'sharokh.png' ],
				// weapons: [[ 'weapon.md2', 'weapon.jpg' ]],
				// animations: {
				// 	move: 'run',
				// 	idle: 'stand',
				// 	jump: 'jump',
				// 	attack: 'attack',
				// 	crouchMove: 'cwalk',
				// 	crouchIdle: 'cstand',
				// 	crouchAttach: 'crattack'
				// },

				// walkSpeed: 105.0,
				// crouchSpeed: 175

				// };

				// const nRows = 1;
				// const nSkins = 1;

				// character = new MD2CharacterComplex();
				// // character.scale = 1;
				// character.controls = controlsOgro;

				// var character1 = new MD2CharacterComplex();
				// // character1.scale = 3;
				// // character1.controls = controlsAll;

				// characterPos = new THREE.Vector3();

				// const baseCharacter = new MD2CharacterComplex();
				// // baseCharacter.scale = 3;

				// baseCharacter.onLoadComplete = function () {

				// 	character.shareParts( baseCharacter );

				// 	// cast and receive shadows
				// 	character.enableShadows( true );

				// 	character.setWeapon( 0 );
				// 	character.setSkin( 5 );

				// 	character.root.position.x = 0;//( i - nSkins / 2 ) * 150;
				// 	character.root.position.z = 0;//j * 250;
				// 	scene.add( character.root );

				// 	character1.shareParts( baseCharacter );
				// 	character1.enableShadows( true );

				// 	character1.setWeapon( 1 );
				// 	character1.setSkin( 2 );

				// 	character1.root.position.x = -450;
				// 	character1.root.position.z = 250;
				// 	character1.root.userData.name="Ppe";
				// 	scene.add( character1.root );

				// 	objects.push(character1.root);
				// 	objects.push(character1.root);

				// };

				// baseCharacter.loadParts( configOgro );
			}

			function initGround(){
				const gt = new THREE.TextureLoader().load( './vendor/mrdoob/three.js/examples/textures/terrain/grasslight-big.jpg' );
				const gg = new THREE.PlaneGeometry( mapSize, mapSize );
				const gm = new THREE.MeshPhongMaterial( { color: 0xffffff, map: gt } );

				ground = new THREE.Mesh( gg, gm );
				ground.rotation.x = - Math.PI / 2;
				ground.material.map.repeat.set( 64, 64 );
				ground.material.map.wrapS = THREE.RepeatWrapping;
				ground.material.map.wrapT = THREE.RepeatWrapping;
				ground.material.map.encoding = THREE.sRGBEncoding;
				// note that because the ground does not cast a shadow, .castShadow is left false
				ground.receiveShadow = true;

				scene.add( ground );
				worldBox.setFromObject(ground);
			}
			//EVENT HANDLERS

			function initEvents(){
				window.addEventListener( 'resize', onWindowResize );
				// document.addEventListener( 'keydown', onKeyDown );
				// document.addEventListener( 'keyup', onKeyUp );
				document.addEventListener( 'pointermove', updatePosCursor );
				document.addEventListener( 'click', event=>{} );

				// document.addEventListener( 'pointerdown', restorePosCursor );
				document.addEventListener( 'keydown', onKeyDown );
				document.addEventListener( 'keyup', onKeyUp );
			}

			const getNewPointOnVector = (p1, p2) => {
				let distAway = cameraControls.minDistance;
				let vector = {x: p2.x - p1.x, y: p2.y - p1.y, z:p2.z - p1.z};
				let vl = Math.sqrt(Math.pow(vector.x, 2) + Math.pow(vector.y, 2) + Math.pow(vector.z, 2));
				let vectorLength = {x: vector.x/vl, y: vector.y/vl, z: vector.z/vl};
				let v = {x: distAway * vectorLength.x, y: distAway * vectorLength.y, z: distAway * vectorLength.z};
				return {x: p2.x + v.x, y: p2.y + v.y, z: p2.z + v.z};
			}

			function updatePosCursor(event){
				// console.log(mixers._root.position)
				if(event.buttons){
					var angle = getNewPointOnVector(cameraControls.object.position,mixers._root.position);
					camera.lookAt(angle.x,angle.y,angle.z);
				}
				
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

			function onKeyDown( event ) {
				// console.log(event)
				switch ( event.code ) {

					case 'ArrowUp':
					case 'KeyW': controlsAll.moveForward = true; break;
					case 'KeyE': 
						controlsAll.moveForward = true; 
						controlsAll.crouch = true; 
					break;

					case 'ArrowDown':
					case 'KeyS': 
						// controlsAll.moveBackward = true; 
						// goinBack = true;
					break;

					case 'ArrowLeft':
					case 'KeyA': controlsAll.moveLeft = true; break;

					case 'ArrowRight':
					case 'KeyD': controlsAll.moveRight = true; break;

					case 'KeyC': controlsAll.crouch = true; break;
					case 'Space': controlsAll.jump = true; break;
					case 'ControlLeft':
					case 'ControlRight': controlsAll.attack = true; break;

				}
				// intersections();
			}

			function onKeyUp( event ) {

				switch ( event.code ) {

					case 'ArrowUp':
					case 'KeyW': controlsAll.moveForward = false; break;

					case 'KeyE': 
						controlsAll.moveForward = false; 
						controlsAll.crouch = false; 
					break;

					case 'ArrowDown':
					case 'KeyS': 
						// controlsAll.moveBackward = false; 
						// goinBack = false;
						break;

					case 'ArrowLeft':
					case 'KeyA': controlsAll.moveLeft = false; break;

					case 'ArrowRight':
					case 'KeyD': controlsAll.moveRight = false; break;

					case 'KeyC': controlsAll.crouch = false; break;
					case 'Space': controlsAll.jump = false; break;
					case 'ControlLeft':
					case 'ControlRight': controlsAll.attack = false; break;

				}
				sendPosition(clock.getDelta());
			}

			function updateMovementModel( delta,el,controls ) {

				function exponentialEaseOut( k ) {

					return k === 1 ? 1 : - Math.pow( 2, - 10 * k ) + 1;

				}

				var obj = el._root.userData;
				// speed based on controls
				var runWalk = controls.crouch?2:1;
				if ( controls.crouch ) 	obj.maxSpeed = obj.crouchSpeed;
				else obj.maxSpeed = obj.walkSpeed;

				obj.maxReverseSpeed = - obj.maxSpeed;

				if ( controls.moveForward ) obj.speed = THREE.MathUtils.clamp( obj.speed + delta * obj.frontAcceleration, obj.maxReverseSpeed, obj.maxSpeed );
				else obj.speed = 0;
				if ( controls.moveBackward ) obj.speed = THREE.MathUtils.clamp( obj.speed - delta * obj.backAcceleration, obj.maxReverseSpeed, obj.maxSpeed );

				// orientation based on controls
				// (don't just stand while turning)

				const dir = 1;

				if ( controls.moveLeft ) {
					obj.bodyOrientation += delta * obj.angularSpeed;
					obj.speed = THREE.MathUtils.clamp( obj.speed + dir * delta * obj.frontAcceleration, obj.maxReverseSpeed, obj.maxSpeed );
				}

				if ( controls.moveRight ) {
					obj.bodyOrientation -= delta * obj.angularSpeed;
					obj.speed = THREE.MathUtils.clamp( obj.speed + dir * delta * obj.frontAcceleration, obj.maxReverseSpeed, obj.maxSpeed );
				}

				// speed decay
				if ( ! ( controls.moveForward || controls.moveBackward ) ) {
					if ( obj.speed > 0 ) {
						const k = exponentialEaseOut( obj.speed / obj.maxSpeed );
						obj.speed = THREE.MathUtils.clamp( obj.speed - k * delta * obj.frontDecceleration, 0, obj.maxSpeed );
					} else {
						const k = exponentialEaseOut( obj.speed / obj.maxReverseSpeed );
						obj.speed = THREE.MathUtils.clamp( obj.speed + k * delta * obj.backAcceleration, obj.maxReverseSpeed, 0 );
					}
				}

				// displacement

				const forwardDelta = obj.speed * delta;

				el._root.position.x += Math.sin( obj.bodyOrientation ) * forwardDelta ;
				el._root.position.z += Math.cos( obj.bodyOrientation ) * forwardDelta ;

				if(obj.speed>0){
					if(!obj.animations[runWalk].isRunning()){
						obj.animations[0].stop();
					}
					if(runWalk==2){
						obj.animations[2].play();
					}else{
						obj.animations[1].play();
					}
				}else{
					if(obj.animations[runWalk].isRunning()){
						obj.animations[0].play();
					}					
					obj.animations[2].stop();					
					obj.animations[1].stop();
				}
				
				el._root.rotation.y = obj.bodyOrientation;

				opObject.rotation.y = el._root.rotation.y;
				opObject.position.x = el._root.position.x;
				opObject.position.y = el._root.position.y+avatarScale;
				opObject.position.z = el._root.position.z;

				if(obj.speed>0){
					moveBall(el)
				}
			}

			function moveBall(el){

				// let scalingFactor = 20;

				let moveX =  el._root.position.x;
				let moveZ =  el._root.position.z;
				let moveY =  0; 

				if( moveX == 0 && moveY == 0 && moveZ == 0) return;

				let resultantImpulse = new Ammo.btVector3( moveX, moveY, moveZ );
				// resultantImpulse.op_mul(scalingFactor);

				let physicsBody = el._root.userData.physicsBody;
				if(physicsBody)
					physicsBody.setLinearVelocity(resultantImpulse);

			}

			function animate() {
				requestAnimationFrame( animate );				
				render();			
				renderer.render( scene, camera );
			}

			function render() {

				const delta = clock.getDelta();		
				
				if(mixers){
					mixers.update( delta );
					updateMovementModel(delta,mixers,controlsAll);
				}

				if(playerCollider)	{	
					updatePlayer(delta);					
					// setLimitWorld(worldBox);
				}

				if(physicsWorld)
					updatePhysics(delta);

				// character.update( delta );
				
				var us = Object.values(userList);
				// console.log(us);
				for(var i=0;i<us.length;i++){
					if(us[i]!="w"){
						us[i].update( delta );
					}
				}
			}

			function updatePhysics( deltaTime ) {
				physicsWorld.stepSimulation(deltaTime, 10);
				// Update objects
				// console.log(physicsWorld)
				for ( let i = 0, il = dynamicObjects.length; i < il; i ++ ) {
					const objThree = dynamicObjects[i];
					const objPhys = objThree.userData.physicsBody;
					const ms = objPhys.getMotionState();
					if ( ms ) {
						ms.getWorldTransform(transformAux1);
						const p = transformAux1.getOrigin();
						// const q = transformAux1.getRotation();
						// console.log(transformAux1)
						// console.log(p.y());
						objThree.position.y = p.y();
						objThree.position.z = p.z();
						objThree.position.x = p.x();

						// objThree.position.set( p.x(), p.y(), p.z() );
						// objThree.quaternion.set( q.x(), q.y(), q.z(), q.w() );
					}
				}
			}

			function setLimitWorld(box){
				// if(mixers._root.position.y > box.max.y){
				// 	mixers._root.position.y = box.max.y;
				// }

				// if(mixers._root.position.y < box.min.y){
				// 	mixers._root.position.y = box.min.y;
				// }
				// if(mixers._root.position.x > box.max.x){
				// 	mixers._root.position.x = box.max.x;
				// }

				// if(mixers._root.position.x < box.min.x){
				// 	mixers._root.position.x = box.min.x;
				// }

				// if(mixers._root.position.z > box.max.z){
				// 	mixers._root.position.z = box.max.z;
				// }

				// if(mixers._root.position.z < box.min.z){
				// 	mixers._root.position.z = box.min.z;
				// }
			}

		</script>

	</body>
</html>