<!DOCTYPE html>
<html lang="en">
	<head>
		<title>three.js webgl - morphtargets - MD2 controls</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
		<link type="text/css" rel="stylesheet" href="main.css">
		<style>
			body {
				background-color: #fff;
				color: #444;
			}
			a {
				color: #08f;
			}
		</style>
	</head>

	<body>
		<div id="info">
			<a href="https://threejs.org" target="_blank" rel="noopener">three.js</a> - MD2 Loader<br />
			use arrows to control characters, mouse for camera
		</div>

		<!-- Import maps polyfill -->
		<!-- Remove this when import maps will be widely supported -->
		<script async src="https://unpkg.com/es-module-shims@1.3.6/dist/es-module-shims.js"></script>

		<script type="importmap">
			{
				"imports": {
					"three": "./vendor/mrdoob/three.js/build/three.module.js"
				}
			}
		</script>

		<script type="module">

			import * as THREE from 'three';

			import Stats from './vendor/mrdoob/three.js/examples/jsm/libs/stats.module.js';

			import { OrbitControls } from './vendor/mrdoob/three.js/examples/jsm/controls/OrbitControls.js';
			// import { PointerLockControls } from './vendor/mrdoob/three.js/examples/jsm/controls/PointerLockControls.js';
			import { MD2CharacterComplex } from './vendor/mrdoob/three.js/examples/jsm/misc/MD2CharacterComplex.js';
			import { Gyroscope } from './vendor/mrdoob/three.js/examples/jsm/misc/Gyroscope.js';

			let SCREEN_WIDTH = window.innerWidth;
			let SCREEN_HEIGHT = window.innerHeight;

			let container, stats;
			let camera, scene, renderer;

			const characters = [];
			let nCharacters = 0;

			let cameraControls;
			var character;

			const controls = {

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

			init();
			animate();

			
			function init() {

				container = document.createElement( 'div' );
				document.body.appendChild( container );

				// CAMERA
				
				camera = new THREE.PerspectiveCamera( 45, window.innerWidth / window.innerHeight, 1, 4000 );
				camera.position.set( 0, 150, 1300 );

				// SCENE

				scene = new THREE.Scene();
				scene.background = new THREE.Color( 0xffffff );
				// scene.fog = new THREE.Fog( 0xffffff, 1000, 4000 );

				scene.add( camera );

				// LIGHTS

				scene.add( new THREE.AmbientLight( 0x222222 ) );

				const light = new THREE.DirectionalLight( 0xffffff, 2.25 );
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


				//  GROUND

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

				// RENDERER

				renderer = new THREE.WebGLRenderer( { antialias: true } );
				renderer.setPixelRatio( window.devicePixelRatio );
				renderer.setSize( SCREEN_WIDTH, SCREEN_HEIGHT );
				container.appendChild( renderer.domElement );

				//

				renderer.outputEncoding = THREE.sRGBEncoding;
				renderer.shadowMap.enabled = true;
				renderer.shadowMap.type = THREE.PCFSoftShadowMap;

				// STATS

				stats = new Stats();
				container.appendChild( stats.dom );

				// CONTROLS

				cameraControls = new OrbitControls( camera, renderer.domElement );
				cameraControls.maxDistance = 1600;
				cameraControls.minDistance = 400;
				cameraControls.maxPolarAngle = Math.PI * 0.5;;
				cameraControls.minPolarAngle = 0;
				cameraControls.rotateSpeed = 0.4;
				cameraControls.minAzimuthAngle = Math.PI *0.5;
				cameraControls.update();

				// CHARACTER

				// scene.add( cameraControls );

// EVENTS

window.addEventListener( 'resize', onWindowResize );
// document.addEventListener( 'keydown', onKeyDown );
// document.addEventListener( 'keyup', onKeyUp );
document.addEventListener( 'pointermove', updatePosCursor );
document.addEventListener( 'click', event=>{
	
} );

// document.addEventListener( 'pointerdown', restorePosCursor );
document.addEventListener( 'keydown', onKeyDown );
document.addEventListener( 'keyup', onKeyUp );

const getNewPointOnVector = (p1, p2) => {
	let distAway = cameraControls.minDistance;
	let vector = {x: p2.x - p1.x, y: p2.y - p1.y, z:p2.z - p1.z};
	let vl = Math.sqrt(Math.pow(vector.x, 2) + Math.pow(vector.y, 2) + Math.pow(vector.z, 2));
	let vectorLength = {x: vector.x/vl, y: vector.y/vl, z: vector.z/vl};
	let v = {x: distAway * vectorLength.x, y: distAway * vectorLength.y, z: distAway * vectorLength.z};
	return {x: p2.x + v.x, y: p2.y + v.y, z: p2.z + v.z};
}

function updatePosCursor(event){
	if(event.buttons){
		var angle = getNewPointOnVector(cameraControls.object.position,character.root.position);
		// console.log(event)
		camera.lookAt(angle.x,angle.y,angle.z);
	}
	
}


				raycaster = new THREE.Raycaster();

				const configOgro = {

					baseUrl: './vendor/mrdoob/three.js/examples/models/md2/ogro/',

					body: 'ogro.md2',
					skins: [ 'grok.jpg', 'ogrobase.png', 'arboshak.png', 'ctf_r.png', 'ctf_b.png', 'darkam.png', 'freedom.png',
							 'gib.png', 'gordogh.png', 'igdosh.png', 'khorne.png', 'nabogro.png',
							 'sharokh.png' ],
					weapons: [[ 'weapon.md2', 'weapon.jpg' ]],
					animations: {
						move: 'run',
						idle: 'stand',
						jump: 'jump',
						attack: 'attack',
						crouchMove: 'cwalk',
						crouchIdle: 'cstand',
						crouchAttach: 'crattack'
					},

					walkSpeed: 350,
					crouchSpeed: 175

				};

				const nRows = 1;
				const nSkins = 1;//configOgro.skins.length;

				// nCharacters = nSkins * nRows;

				// for ( let i = 0; i < nCharacters; i ++ ) {

				character = new MD2CharacterComplex();
				character.scale = 3;
				character.controls = controls;

				var character1 = new MD2CharacterComplex();
				character1.scale = 3;
				character1.controls = controls;

				characterPos = new THREE.Vector3();
				// characters.push( character );

				// }
				

				const baseCharacter = new MD2CharacterComplex();
				baseCharacter.scale = 3;

				baseCharacter.onLoadComplete = function () {

					// let k = 0;

					// for ( let j = 0; j < nRows; j ++ ) {

					// 	for ( let i = 0; i < nSkins; i ++ ) {

					// const cloneCharacter = character;

					character.shareParts( baseCharacter );

					// cast and receive shadows
					character.enableShadows( true );

					character.setWeapon( 0 );
					character.setSkin( 0 );

					character.root.position.x = 0;//( i - nSkins / 2 ) * 150;
					character.root.position.z = 0;//j * 250;
					scene.add( character.root );
					characterLastPosition.copy(character.root.position)

					
					character1.shareParts( baseCharacter );
					character1.enableShadows( true );

					character1.setWeapon( 1 );
					character1.setSkin( 2 );

					character1.root.position.x = -450;//( i - nSkins / 2 ) * 150;
					character1.root.position.z = 250;//j * 250;

					scene.add( character1.root );

					gyro = new Gyroscope();
					gyro.add( camera );
					gyro.add( light, light.target );
					character.root.add( gyro );
					// character1.layers = 1;

					const boxAux = new THREE.Box3();
					boxAux.setFromObject(character1.root,true)
					objects.push(boxAux);
				};

				baseCharacter.loadParts( configOgro );
				

				worldBox.setFromObject(ground);

				const boxGeometry = new THREE.BoxGeometry( 200, 200, 200 ).toNonIndexed();

				

				let position = boxGeometry.attributes.position;
				const colorsBox = [];

				for ( let i = 0, l = position.count; i < l; i ++ ) {

					color.setHSL( Math.random() * 0.3 + 0.5, 0.75, Math.random() * 0.25 + 0.75 );
					colorsBox.push( color.r, color.g, color.b );

				}

				boxGeometry.setAttribute( 'color', new THREE.Float32BufferAttribute( colorsBox, 3 ) );

				for ( let i = 0; i < 10; i ++ ) {

					const boxMaterial = new THREE.MeshPhongMaterial( { specular: 0xffffff, flatShading: true, vertexColors: true } );
					boxMaterial.color.setHSL( Math.random() * 0.2 + 0.5, 0.75, Math.random() * 0.25 + 0.75 );

					const box = new THREE.Mesh( boxGeometry, boxMaterial );
					box.position.x = Math.floor( Math.random() * 20 - 10 ) * cameraControls.minDistance;
					box.position.y = 100;
					box.position.z = Math.floor( Math.random() * 20 - 10 ) * cameraControls.minDistance;

					scene.add( box );
					const boxAux = new THREE.Box3();
					boxAux.setFromObject(box,true)
					objects.push(boxAux);
				}

				// 
				// cameraControls.target.set( 0,150,1300 ); 
				
				// console.log(scene.children)

			}
			// EVENT HANDLERS

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
					case 'KeyW': controls.moveForward = true; break;

					case 'ArrowDown':
					case 'KeyS': controls.moveBackward = true; break;

					case 'ArrowLeft':
					case 'KeyA': controls.moveLeft = true; break;

					case 'ArrowRight':
					case 'KeyD': controls.moveRight = true; break;

					case 'KeyC': controls.crouch = true; break;
					case 'Space': controls.jump = true; break;
					case 'ControlLeft':
					case 'ControlRight': controls.attack = true; break;

				}

			}

			function onKeyUp( event ) {

				switch ( event.code ) {

					case 'ArrowUp':
					case 'KeyW': controls.moveForward = false; break;

					case 'ArrowDown':
					case 'KeyS': controls.moveBackward = false; break;

					case 'ArrowLeft':
					case 'KeyA': controls.moveLeft = false; break;

					case 'ArrowRight':
					case 'KeyD': controls.moveRight = false; break;

					case 'KeyC': controls.crouch = false; break;
					case 'Space': controls.jump = false; break;
					case 'ControlLeft':
					case 'ControlRight': controls.attack = false; break;

				}
				
			}

			function m(x,y,z){
				return Math.sqrt(x*x+y*y+z*z)
			}

			function distancia_x_y(p,q){
				var px=p.x;
				var py=p.y;
				var pz=p.z;

				var qx=q.x;
				var qy=q.y;
				var qz=q.z;

				var pqx=qx-px;
				var pqy=qy-py;
				var pqz=qz-pz;
				return m(pqx,pqy,pqz);
			}

			//
			function compareBox (b1,b2){
				return b2.max.x < b1.min.x || b2.min.x > b1.max.x ||
			b2.max.y < b1.min.y || b2.min.y > b1.max.y ||
			b2.max.z < b1.min.z || b2.min.z > b1.max.z ? false : true;
			}

			function intersections(){
				var inside = false;

				const boxAux = new THREE.Box3();
				boxAux.setFromObject(character.root,true);

				for(var i =0;i<objects.length;i++){
					inside = compareBox(boxAux,objects[i]);
					// inside = boxAux.intersectsBox(objects[i]);
					if(inside){						
						break;
					}
				}
				// console.log(inside)
				if(!inside){
					characterLastPosition.copy(character.root.position);
				}else{
					// console.log(objects[i])
					character.root.position.copy(characterLastPosition);
				}
				
			}

			function animate() {
				requestAnimationFrame( animate );

				intersections();
				renderer.render( scene, camera );
				render();

			}

			function render() {

				const delta = clock.getDelta();
				
				character.update( delta );
				
				setLimitWorld(worldBox);
				
				renderer.render( scene, camera );

			}

			function setLimitWorld(box){
				if(character.root.position.x > box.max.x){
					character.root.position.x = box.max.x;
				}

				if(character.root.position.x < box.min.x){
					character.root.position.x = box.min.x;
				}

				if(character.root.position.z > box.max.z){
					character.root.position.z = box.max.z;
				}

				if(character.root.position.z < box.min.z){
					character.root.position.z = box.min.z;
				}
			}

		</script>

	</body>
</html>