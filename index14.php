<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Game Import</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
        <style>
            body { margin: 0; }
            .el{
                position:absolute;
            }
        </style>
    </head>
    <body>
		<div class="el">
            <button id="import">Load</button>
        </div>
		<script src="./vendor/mrdoob/three.js/examples/js/libs/ammo.wasm.js"></script>
        <script type="importmap">
			{
				"imports": {
					"three": "./vendor/mrdoob/three.js/build/three.module.js"
				}
			}
		</script>
        <script async src="./otros/es-module-shims.js"></script>
        
        <script type="module">
            import * as THREE from "three";

            //controls
            import { OrbitControls } from './vendor/mrdoob/three.js/examples/jsm/controls/OrbitControls.js';
            import { ArcballControls  } from './vendor/mrdoob/three.js/examples/jsm/controls/ArcballControls.js';
            import { GUI } from './vendor/mrdoob/three.js/examples/jsm/libs/lil-gui.module.min.js';
            
            import { GLTFExporter } from './vendor/mrdoob/three.js/examples/jsm/exporters/GLTFExporter.js';
            import { OBJExporter } from './vendor/mrdoob/three.js/examples/jsm/exporters/OBJExporter.js';

            
            //variable declaration section
            var scene, camera, renderer;
            var moveDirection = { left: 0, right: 0, forward: 0, back: 0 }

            var clock;
            let terrainMesh;
            var terrainWidth = 256;
			var terrainDepth = 256;
            let container, stats;
            var gyro,light,cameraControls;
            var mixers = {};

            var gltfMain;
            var avatarMoving = false;

            var mapSize = 16000;

            let raycaster,raycaster1;
            var worldScale = 0.1;

            var heightData,terrainWidthExtents=200,terrainDepthExtents=200;

            //edit terrain
            var vector2,vector3;
            var controlEnable = true;
            const cameras = [ 'Orthographic', 'Perspective' ];
			const cameraType = { type: 'Perspective' };
            var gui;
            let folderOptions, folderAnimations, properties;

            Ammo().then( function ( AmmoLib ) {
				Ammo = AmmoLib;
				start();
			});

            function start (){
                vector2 = new THREE.Vector2();
                vector3 = new THREE.Vector3();
                properties = {
                    size:10,
                    up:true
                };
                
                setupGraphics();
                // createKinematicBox();
                loadMap();
                initEvents();                
            }

            function onWindowResize() {
                renderer.setSize( window.innerWidth, window.innerHeight );
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
            }

            function loadMap() {
                const geometry = new THREE.PlaneGeometry( terrainWidthExtents, terrainDepthExtents, terrainWidth - 1, terrainDepth - 1 );
				geometry.rotateX( - Math.PI / 2 );

				const groundMaterial = new THREE.MeshPhongMaterial( { color: 0xC7C7C7 } );
                groundMaterial.side = THREE.DoubleSide;
                groundMaterial.displacementScale = 2;
                groundMaterial.roughness = 0;

				terrainMesh = new THREE.Mesh( geometry, groundMaterial );
				terrainMesh.receiveShadow = true;
				terrainMesh.castShadow = true;
				terrainMesh.scale.multiplyScalar(worldScale);
                terrainMesh.name = "ground";
				scene.add( terrainMesh );
				terrainMesh.position.set( 0, -(worldScale*3)+1, 0 );

				const textureLoader = new THREE.TextureLoader();
				textureLoader.load( './maps/map.png', function ( texture ) {
					texture.wrapS = THREE.RepeatWrapping;
					texture.wrapT = THREE.RepeatWrapping;
					// texture.side = THREE.DoubleSide;
					groundMaterial.map = texture;
					groundMaterial.needsUpdate = true;
				} );
                
                return;
            }

            function setupGraphics(){
                container = document.createElement( 'div' );
				document.body.appendChild( container );
                //create clock for timing
                clock = new THREE.Clock();

                //create the scene
                scene = new THREE.Scene();
                scene.background = new THREE.Color( 0xbfd1e5 );

                //create camera
                // camera = new THREE.PerspectiveCamera( 60, window.innerWidth / window.innerHeight, 0.2, 5000 );
                camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 1, 10000);
                camera.position.set(0, 1, 45);
                scene.add(camera)
                // camera.lookAt(new THREE.Vector3(0, 0, 0));

                //Add hemisphere light
                var hemiLight = new THREE.HemisphereLight( 0xffffff, 0xffffff, 1 );
                hemiLight.color.setHSL( 0.6, 0.6, 0.6 );
                hemiLight.groundColor.setHSL( 0.1, 1, 0.4 );
                hemiLight.position.set( 0, 50, 0 );
                scene.add( hemiLight );

                //Add directional light
                // LIGHTS
				scene.add( new THREE.AmbientLight( 0x222222 ) );

                light = new THREE.DirectionalLight( 0xffffff, 0.25 );
                light.position.set( 200, 400, 500 );

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

                light.castShadow = true;

                light.shadow.mapSize.width = 2048;
                light.shadow.mapSize.height = 2048;

                var d = 50;

                light.shadow.camera.left = -d;
                light.shadow.camera.right = d;
                light.shadow.camera.top = d;
                light.shadow.camera.bottom = -d;

                light.shadow.camera.far = 13500;

                //Setup the renderer
                // RENDERER
                renderer = new THREE.WebGLRenderer({ antialias: true });
                renderer.setPixelRatio(window.devicePixelRatio);
                renderer.setSize(window.innerWidth, window.innerHeight);
                renderer.shadowMap.enabled = true;

                container.appendChild(renderer.domElement);

                renderer.outputEncoding = THREE.sRGBEncoding;
                renderer.shadowMap.enabled = true;
                cameraControls = new OrbitControls( camera, renderer.domElement );
				// cameraControls.movementSpeed = 100;
				// cameraControls.domElement = renderer.domElement;
				// // cameraControls.rollSpeed = Math.PI / 24;
				cameraControls.enableRotate = true;
				cameraControls.autoRotate = false;


                raycaster1 = new THREE.Raycaster();
                
                initEvents();
                animate();
                loadControls();
            }

            function loadControls(){
                const arcballGui = {
                    gizmoVisible: true,
                    setArcballControls: function () {
                        // controls = new ArcballControls( camera, renderer.domElement, scene );
                        // controls.addEventListener( 'change', render );
                        this.gizmoVisible = true;
                        this.populateGui();
                    },

                    populateGui: function () {

                        folderOptions.add( cameraControls, 'enabled' ).name( 'Enable controls' );
                        // folderOptions.add( cameraControls, 'enableGrid' ).name( 'Enable Grid' );
                        folderOptions.add( cameraControls, 'enableRotate' ).name( 'Enable rotate' );
                        folderOptions.add( cameraControls, 'enablePan' ).name( 'Enable pan' );
                        folderOptions.add( cameraControls, 'enableZoom' ).name( 'Enable zoom' );
                        // folderOptions.add( cameraControls, 'cursorZoom' ).name( 'Cursor zoom' );
                        // folderOptions.add( cameraControls, 'adjustNearFar' ).name( 'adjust near/far' );
                        // folderOptions.add( cameraControls, 'scaleFactor', 1.1, 10, 0.1 ).name( 'Scale factor' );
                        // folderOptions.add( cameraControls, 'minDistance', 0, 50, 0.5 ).name( 'Min distance' );
                        // folderOptions.add( cameraControls, 'maxDistance', 0, 50, 0.5 ).name( 'Max distance' );
                        // folderOptions.add( cameraControls, 'minZoom', 0, 50, 0.5 ).name( 'Min zoom' );
                        // folderOptions.add( cameraControls, 'maxZoom', 0, 50, 0.5 ).name( 'Max zoom' );
                        // folderOptions.add( arcballGui, 'gizmoVisible' ).name( 'Show gizmos' ).onChange( function () {

                        //     cameraControls.setGizmosVisible( arcballGui.gizmoVisible );

                        // } );
                        // folderOptions.add( cameraControls, 'copyState' ).name( 'Copy state(ctrl+c)' );
                        // folderOptions.add( cameraControls, 'pasteState' ).name( 'Paste state(ctrl+v)' );
                        // folderOptions.add( cameraControls, 'reset' ).name( 'Reset' );
                        // folderAnimations.add( cameraControls, 'enableAnimations' ).name( 'Enable anim.' );
                        folderAnimations.add( cameraControls, 'dampingFactor', 0, 100, 1 ).name( 'Damping' );
                        // folderAnimations.add( cameraControls, 'wMax', 0, 100, 1 ).name( 'Angular spd' );
                        
                        //brush setting
                        folderAnimations.add( properties, 'size', 1, 100, 10 ).name( 'Size' );
                        folderAnimations.add( properties, 'up' ).name( 'Up/Down' );

                    }

                };

                gui = new GUI();
                // gui.add( cameraType, 'type', cameras ).name( 'Choose Camera' ).onChange( function () {
                //     // setCamera( cameraType.type );
                // } );

                folderOptions = gui.addFolder( 'Arcball parameters' );
                folderAnimations = folderOptions.addFolder( 'Animations' );

                arcballGui.setArcballControls();
                // console.log(folderOptions);
            }

            //EVENT HANDLERS
            function initEvents() {
                window.addEventListener('resize', onWindowResize);
                document.addEventListener('pointermove', event => {
                    if (event.buttons && !cameraControls.enabled) {
                        onClickadd(event);
                        event.preventDefault = true;
                    }
                });

                document.addEventListener('keydown', onKeyDown);
                document.addEventListener('keyup', onKeyUp);
                // document.addEventListener('click', onClickadd);
                document.getElementById("import").addEventListener('click',()=>{loadVMap()})
            }
            
            function onClickadd(event){
                // scene.layers.set(0);
                
                vector2.x = (event.clientX / window.innerWidth) * 2 - 1;
                vector2.y = -(event.clientY / window.innerHeight) * 2 + 1;
                // console.log(mouse)
                raycaster1.setFromCamera(vector2, camera);
                raycaster1.far = 2000;
                // raycaster1.ray.origin.copy(mixers._root.position);
                // console.log(scene)
                var  intersects = raycaster1.intersectObject(terrainMesh,true);

                //here comes event
                if( intersects.length > 0 && intersects[0].object.geometry ){                    
                    const mesh = intersects[0].object
                    const geometry = mesh.geometry
                    const point = intersects[0].point

                    for (let i = 0; i  < geometry.attributes.position.count; i++) {
                        vector3.setX(geometry.attributes.position.getX(i))
                        vector3.setY(geometry.attributes.position.getY(i))
                        vector3.setZ(geometry.attributes.position.getZ(i))
                        const toWorld = mesh.localToWorld(vector3)

                        const distance = point.distanceTo(toWorld)
                        if (distance < properties.size) {
                            if(properties.up)
                                geometry.attributes.position.setY(i, geometry.attributes.position.getY(i) + (properties.size - distance) / properties.size*2)
                            else{                                
                                geometry.attributes.position.setY(i, geometry.attributes.position.getY(i) - (properties.size - distance) / properties.size*2)
                            }
                        }
                    }
                    geometry.computeVertexNormals()
                    geometry.attributes.position.needsUpdate = true
                }
            }

            function onKeyDown(event) {
                console.log(event.code);
                switch (event.code) {
                    case "Digit1":{
                        cameraControls.enabled = !cameraControls.enabled;
                        break;
                    }
                    case 'ControlLeft':{
                        cameraControls.enabled = false;
                        break;
                    }
                    case 'ArrowUp':
                    case 'KeyW': 
                        // controlsAll.moveForward = true;                        
                        // moveDirection.forward = 1; 
                        break;
                    case 'KeyE':
                        // controlsAll.moveForward = true;
                        // controlsAll.crouch = true;
                        // moveDirection.forward = 1;
                        break;

                    case 'ArrowDown':
                    case 'KeyS':
                        // controlsAll.moveBackward = true;
                        // controlsAll.goinBack = true;
                        // moveDirection.back = 1;
                        break;

                    case 'ArrowLeft':
                    case 'KeyA': 
                        // controlsAll.moveLeft = true; 
                        // moveDirection.left = 1
                        break;

                    case 'ArrowRight':
                    case 'KeyD': 
                        // controlsAll.moveRight = true; 
                        // moveDirection.right = 1
                        break;

                    case 'KeyC': 
                        // controlsAll.crouch = true; 
                        break;
                    case 'Space':
                        //  controlsAll.jump = true; 
                         break;
                    case 'ControlLeft':
                    case 'ControlRight': 
                        // controlsAll.attack = true; 
                        break;
                }
            }

            function onKeyUp(event){
                switch (event.code) {                    
                    case 'ControlLeft':{
                        cameraControls.enabled = true;
                        break;
                    }
                    case 'ArrowUp':
                    case 'KeyW': 
                        // controlsAll.moveForward = true;                        
                        // moveDirection.forward = 1; 
                        break;
                    case 'KeyE':
                        // controlsAll.moveForward = true;
                        // controlsAll.crouch = true;
                        // moveDirection.forward = 1;
                        break;

                    case 'ArrowDown':
                    case 'KeyS':
                        // controlsAll.moveBackward = true;
                        // controlsAll.goinBack = true;
                        // moveDirection.back = 1;
                        break;

                    case 'ArrowLeft':
                    case 'KeyA': 
                        // controlsAll.moveLeft = true; 
                        // moveDirection.left = 1
                        break;

                    case 'ArrowRight':
                    case 'KeyD': 
                        // controlsAll.moveRight = true; 
                        // moveDirection.right = 1
                        break;

                    case 'KeyC': 
                        // controlsAll.crouch = true; 
                        break;
                    case 'Space':
                        //  controlsAll.jump = true; 
                         break;
                    case 'ControlLeft':
                    case 'ControlRight': 
                        // controlsAll.attack = true; 
                        break;
                }
            }

            const getNewPointOnVector = (p1, p2) => {
                let distAway = 0;
                let vector = { x: p2.x - p1.x, y: p2.y - p1.y, z: p2.z - p1.z };
                let vl = Math.sqrt(Math.pow(vector.x, 2) + Math.pow(vector.y, 2) + Math.pow(vector.z, 2));
                let vectorLength = { x: vector.x / vl, y: vector.y / vl, z: vector.z / vl };
                let v = { x: distAway * vectorLength.x, y: distAway * vectorLength.y, z: distAway * vectorLength.z };
                return { x: p2.x + v.x, y: p2.y + v.y + 2, z: p2.z + v.z };
            }
            
            function animate() {
                requestAnimationFrame(animate);
                // render();
                renderer.render(scene, camera);
                cameraControls.update( clock.getDelta() );
            }

            function loadVMap(map='maps/map00.json'){
                var loader = new THREE.FileLoader();
                loader.load(map,function(json){
                    heightData = JSON.parse(json).data; 
                    
                    const vertices = terrainMesh.geometry.attributes.position.array;
                    for ( let i = 0, j = 0, l = vertices.length; i < l; i ++, j += 3 ) {
                        vertices[ j + 1 ] = heightData[ i ];
                    }
                    terrainMesh.geometry.computeVertexNormals()
                    terrainMesh.geometry.attributes.position.needsUpdate = true;
                });    
            }

            function saveJson(){
                var result=scene.toJSON();
                var output =JSON.stringify(result);
                saveArrayBuffer(output, 'scene.json', 'application/json');
            }

            function saveFile() {
                return saveJson();
                const exporter = new GLTFExporter();
                exporter.parse(
                    terrainMesh,
                    function (result) {
                        saveArrayBuffer(result, 'scene.glb');
                    },
                    { binary: true, trs: true, includeCustomExtensions: true, forceIndices: 1 }
                );
                // var ex = exporter.parse(terrainMesh);
                // saveArrayBuffer(ex, 'scene.gltf');
            }

            function saveArrayBuffer(buffer, filename,output='application/octet-stream') {
                save(new Blob([buffer], { type: output }), filename);
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

        </script>    
</body></html>