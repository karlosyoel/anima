<head><style>
    canvas{
        position: absolute;
        top: 0px;
    }
</style></head>
<body wfd-invisible="true">
		<div id="container"><canvas style="display: block; width: 741px; height: 703px; touch-action: none;" data-engine="three.js r142" width="926" height="878"></canvas><div style="position: absolute; top: 0px; left: 0px; cursor: pointer; opacity: 0.9; z-index: 10000;"><canvas style="width: 80px; height: 48px; display: block;" width="80" height="48"></canvas><canvas style="width: 80px; height: 48px; display: none;" wfd-invisible="true" width="80" height="48"></canvas></div></div>
		<div id="info">Ammo.js physics terrain heightfield demo</div>

		<script src="./vendor/mrdoob/three.js/examples/js/libs/ammo.wasm.js" wfd-invisible="true"></script>

		<!-- Import maps polyfill -->
		<!-- Remove this when import maps will be widely supported -->
		<script async="" src="https://unpkg.com/es-module-shims@1.3.6/dist/es-module-shims.js" wfd-invisible="true"></script>

		<script type="importmap" wfd-invisible="true">
			{
				"imports": {
					"three": "./vendor/mrdoob/three.js/build/three.module.js"
				}
			}
		</script>

		<script type="module" wfd-invisible="true">

			import * as THREE from 'three';

			import Stats from './vendor/mrdoob/three.js/examples/jsm/libs/stats.module.js';

			import { OrbitControls } from './vendor/mrdoob/three.js/examples/jsm/controls/OrbitControls.js';

			// Heightfield parameters
			var terrainWidthExtents = 200;
			var terrainDepthExtents = 200;
			var terrainWidth = 256;
			var terrainDepth = 256;
			var terrainHalfWidth = terrainWidth / 2;
			var terrainHalfDepth = terrainDepth / 2;
			const terrainMaxHeight = 10;
			const terrainMinHeight = - 4;

			// Graphics variables
			let container, stats;
			let camera, scene, renderer;
			let terrainMesh;
			const clock = new THREE.Clock();

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

			let time = 0;
			const objectTimePeriod = 3;
			let timeNextSpawn = time + objectTimePeriod;
			const maxNumObjects = 50;

			Ammo().then( function ( AmmoLib ) {

				Ammo = AmmoLib;

				init();
				animate();

			} );

			function init() {

				// heightData = generateHeight( terrainWidth, terrainDepth, terrainMinHeight, terrainMaxHeight );
				// console.log(heightData);
				heightData = generateHeightPoints();
				// console.log(heightData);
				initGraphics();

				initPhysics();

			}

			function initGraphics() {

				container = document.getElementById( 'container' );

				renderer = new THREE.WebGLRenderer();
				renderer.setPixelRatio( window.devicePixelRatio );
				renderer.setSize( window.innerWidth, window.innerHeight );
				renderer.shadowMap.enabled = true;
				container.appendChild( renderer.domElement );

				stats = new Stats();
				stats.domElement.style.position = 'absolute';
				stats.domElement.style.top = '0px';
				container.appendChild( stats.domElement );

				camera = new THREE.PerspectiveCamera( 60, window.innerWidth / window.innerHeight, 0.2, 10000 );

				scene = new THREE.Scene();
				scene.background = new THREE.Color( 0xbfd1e5 );

				camera.position.y = heightData[ terrainHalfWidth + terrainHalfDepth * terrainWidth ] * ( terrainMaxHeight - terrainMinHeight ) + 5;

				camera.position.z = terrainDepthExtents / 2;
				camera.lookAt( 0, 0, 0 );

				const controls = new OrbitControls( camera, renderer.domElement );
				controls.enableZoom = true;

				const geometry = new THREE.PlaneGeometry( terrainWidthExtents, terrainDepthExtents, terrainWidth - 1, terrainDepth - 1 );
				geometry.rotateX( - Math.PI / 2 );
                console.log(geometry)
				const vertices = geometry.attributes.position.array;

				for ( let i = 0, j = 0, l = vertices.length; i < l; i ++, j += 3 ) {

					// j + 1 because it is the y component that we modify
					vertices[ j + 1 ] = heightData[ i ];

				}

				geometry.computeVertexNormals();

				const groundMaterial = new THREE.MeshPhongMaterial( { color: 0xC7C7C7 } );
				terrainMesh = new THREE.Mesh( geometry, groundMaterial );
				terrainMesh.receiveShadow = true;
				terrainMesh.castShadow = true;
				terrainMesh.scale.multiplyScalar(2);
				scene.add( terrainMesh );
				terrainMesh.position.set( 0, -6, 0 );

				const textureLoader = new THREE.TextureLoader();
				textureLoader.load( './maps/map.png', function ( texture ) {
					texture.wrapS = THREE.RepeatWrapping;
					texture.wrapT = THREE.RepeatWrapping;
					texture.side = THREE.DoubleSide;
					// texture.repeat.set( terrainWidth - 1, terrainDepth - 1 );
					groundMaterial.map = texture;
					groundMaterial.needsUpdate = true;
				} );

				const light = new THREE.DirectionalLight( 0xffffff, 1 );
				light.position.set( 100, 100, 50 );
				light.castShadow = true;
				const dLight = 200;
				const sLight = dLight * 0.25;
				light.shadow.camera.left = - sLight;
				light.shadow.camera.right = sLight;
				light.shadow.camera.top = sLight;
				light.shadow.camera.bottom = - sLight;

				light.shadow.camera.near = dLight / 30;
				light.shadow.camera.far = dLight;

				light.shadow.mapSize.x = 1024 * 2;
				light.shadow.mapSize.y = 1024 * 2;

				scene.add( light );


				window.addEventListener( 'resize', onWindowResize );

			}

			function onWindowResize() {
				camera.aspect = window.innerWidth / window.innerHeight;
				camera.updateProjectionMatrix();
				renderer.setSize( window.innerWidth, window.innerHeight );
			}

			function initPhysics() {

				// Physics configuration

				collisionConfiguration = new Ammo.btDefaultCollisionConfiguration();
				dispatcher = new Ammo.btCollisionDispatcher( collisionConfiguration );
				broadphase = new Ammo.btDbvtBroadphase();
				solver = new Ammo.btSequentialImpulseConstraintSolver();
				physicsWorld = new Ammo.btDiscreteDynamicsWorld( dispatcher, broadphase, solver, collisionConfiguration );
				physicsWorld.setGravity( new Ammo.btVector3( 0, - 9.8, 0 ) );

				// Create the terrain body

				const groundShape = createTerrainShape();
				const groundTransform = new Ammo.btTransform();
				groundTransform.setIdentity();
				// Shifts the terrain, since bullet re-centers it on its bounding box.
				groundTransform.setOrigin( new Ammo.btVector3( 0, 0, 0 ) );
				const groundMass = 0;
				const groundLocalInertia = new Ammo.btVector3( 0, 0, 0 );
				const groundMotionState = new Ammo.btDefaultMotionState( groundTransform );
				const groundBody = new Ammo.btRigidBody( new Ammo.btRigidBodyConstructionInfo( groundMass, groundMotionState, groundShape, groundLocalInertia ) );
				physicsWorld.addRigidBody( groundBody );

				transformAux1 = new Ammo.btTransform();

			}

			function generateHeightPoints() {
				var img = new Image();
				// img.onload = run;
				img.src = './maps/heightmap.png';

				var ctx = document.createElement("canvas").getContext("2d"); //using 2d canvas to read image
				ctx.canvas.width = img.width;
				ctx.canvas.height = img.height;
				ctx.drawImage(img, 0, 0);
				var imgData = ctx.getImageData(0, 0, ctx.canvas.width, ctx.canvas.height);

				// console.log(imgData)
				terrainWidth = imgData.width - 1;
				terrainDepth = imgData.height - 1;

				// terrainHalfWidth = terrainWidth / 2;
				// terrainHalfDepth = terrainDepth / 2;

				// terrainWidthExtents = terrainWidth - 50;
				// terrainDepthExtents = terrainDepth - 50;

				const size = terrainWidth * terrainDepth;
				const data = new Float32Array( size );
				var p = 0;
				for (var z = 0; z <= terrainDepth; ++z) {
					for (var x = 0; x <= terrainWidth; ++x) {
						var offset = (z * imgData.width + x) * 4;
						var height = imgData.data[offset] * 15 / 255;
						data[p] = height;//(x, height, z);
						p ++;
					}
				}
				return data;
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
					heightScale*2,
					terrainMinHeight,
					terrainMaxHeight,
					upAxis,
					hdt,
					flipQuadEdges
				);

				// Set horizontal scale
				const scaleX = terrainWidthExtents / ( terrainWidth - 1 );
				const scaleZ = terrainDepthExtents / ( terrainDepth - 1 );
				heightFieldShape.setLocalScaling( new Ammo.btVector3( scaleX*2, 1*2, scaleZ*2 ) );

				heightFieldShape.setMargin( 0.05 );

				return heightFieldShape;

			}

			function generateObject() {

				const numTypes = 4;
				const objectType = Math.ceil( Math.random() * numTypes );

				let threeObject = null;
				let shape = null;

				const objectSize = 3;
				const margin = 0.05;

				let radius, height;

				switch ( objectType ) {

					case 1:
						// Sphere
						radius = 1 + Math.random() * objectSize;
						threeObject = new THREE.Mesh( new THREE.SphereGeometry( radius, 20, 20 ), createObjectMaterial() );
						shape = new Ammo.btSphereShape( radius );
						shape.setMargin( margin );
						break;
					case 2:
						// Box
						const sx = 1 + Math.random() * objectSize;
						const sy = 1 + Math.random() * objectSize;
						const sz = 1 + Math.random() * objectSize;
						threeObject = new THREE.Mesh( new THREE.BoxGeometry( sx, sy, sz, 1, 1, 1 ), createObjectMaterial() );
						shape = new Ammo.btBoxShape( new Ammo.btVector3( sx * 0.5, sy * 0.5, sz * 0.5 ) );
						shape.setMargin( margin );
						break;
					case 3:
						// Cylinder
						radius = 1 + Math.random() * objectSize;
						height = 1 + Math.random() * objectSize;
						threeObject = new THREE.Mesh( new THREE.CylinderGeometry( radius, radius, height, 20, 1 ), createObjectMaterial() );
						shape = new Ammo.btCylinderShape( new Ammo.btVector3( radius, height * 0.5, radius ) );
						shape.setMargin( margin );
						break;
					default:
						// Cone
						radius = 1 + Math.random() * objectSize;
						height = 2 + Math.random() * objectSize;
						threeObject = new THREE.Mesh( new THREE.ConeGeometry( radius, height, 20, 2 ), createObjectMaterial() );
						shape = new Ammo.btConeShape( radius, height );
						break;

				}

				threeObject.position.set( ( Math.random() - 0.5 ) * 250 * 0.6, terrainMaxHeight + objectSize + 2, ( Math.random() - 0.5 ) * 250 * 0.6 );

				const mass = objectSize * 5;
				const localInertia = new Ammo.btVector3( 0, 0, 0 );
				shape.calculateLocalInertia( mass, localInertia );
				const transform = new Ammo.btTransform();
				transform.setIdentity();
				const pos = threeObject.position;
				transform.setOrigin( new Ammo.btVector3( pos.x, pos.y, pos.z ) );
				const motionState = new Ammo.btDefaultMotionState( transform );
				const rbInfo = new Ammo.btRigidBodyConstructionInfo( mass, motionState, shape, localInertia );
				const body = new Ammo.btRigidBody( rbInfo );

				threeObject.userData.physicsBody = body;

				threeObject.receiveShadow = true;
				threeObject.castShadow = true;

				scene.add( threeObject );
				dynamicObjects.push( threeObject );

				physicsWorld.addRigidBody( body );



			}

			function createObjectMaterial() {

				const c = Math.floor( Math.random() * ( 1 << 24 ) );
				return new THREE.MeshPhongMaterial( { color: c } );

			}

			function animate() {

				requestAnimationFrame( animate );

				render();
				stats.update();

			}

			function render() {

				const deltaTime = clock.getDelta();

				if ( dynamicObjects.length < maxNumObjects && time > timeNextSpawn ) {

					generateObject();
					timeNextSpawn = time + objectTimePeriod;

				}

				updatePhysics( deltaTime );

				renderer.render( scene, camera );

				time += deltaTime;

			}

			function updatePhysics( deltaTime ) {

				physicsWorld.stepSimulation( deltaTime, 10 );

				// Update objects
				for ( let i = 0, il = dynamicObjects.length; i < il; i ++ ) {

					const objThree = dynamicObjects[ i ];
					const objPhys = objThree.userData.physicsBody;
					const ms = objPhys.getMotionState();
					if ( ms ) {

						ms.getWorldTransform( transformAux1 );
						const p = transformAux1.getOrigin();
						const q = transformAux1.getRotation();
                        // console.log(p.y(),p.x(),p.z())
						objThree.position.set( p.x(), p.y(), p.z() );
						objThree.quaternion.set( q.x(), q.y(), q.z(), q.w() );
					}
				}
			}

		</script>

	

</body>