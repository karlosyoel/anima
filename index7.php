<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Game</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
        <style>
            body { margin: 0; }
        </style>
    </head>
    <body>
		
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
            import { OBJLoader } from './vendor/mrdoob/three.js/examples/jsm/loaders/OBJLoader.js';
            import { OrbitControls } from './vendor/mrdoob/three.js/examples/jsm/controls/OrbitControls.js';
            import { Gyroscope } from './vendor/mrdoob/three.js/examples/jsm/misc/Gyroscope.js';
            
            //avatar
            import { GLTFLoader } from './vendor/mrdoob/three.js/examples/jsm/loaders/GLTFLoader.js';
            import * as SkeletonUtils from './vendor/mrdoob/three.js/examples/jsm/utils/SkeletonUtils.js';
            //map
            import { fragmentShader,vertexShader } from './otros/ShaderTerrain.js';

            //variable declaration section
            var physicsWorld, scene, camera, renderer, rigidBodies = [], tmpTrans = null,tmpMS;
            var ballObject = null, moveDirection = { left: 0, right: 0, forward: 0, back: 0 }
            var kObject = null, kMoveDirection = { left: 0, right: 0, forward: 0, back: 0 }, tmpPos = new THREE.Vector3(), tmpQuat = new THREE.Quaternion();
            var ammoTmpPos = null, ammoTmpQuat = null;

            const STATE = { DISABLE_DEACTIVATION : 4 }

            const FLAGS = { CF_KINEMATIC_OBJECT: 2 }

            var clock;
            let terrainMesh;
            const terrainWidth = 256;
			const terrainDepth = 256;
            let container, stats;
            var gyro,light,cameraControls;
            var mixers = {};

            const loader = new GLTFLoader();
            var gltfMain;
            var avatarMoving = false;
            var avatarScale = 3;
            var physicsBody;
            var playerCollider;
            var characterLastPosition = new THREE.Vector3();
            var scale = {x:0,y:0,z:0};
            var opObject;
            const dynamicObjects = [];

            const controlsAll = {
                moveForward: false,
                moveBackward: false,
                moveLeft: false,
                moveRight: false,
                goinBack: false
            };

            var auxWs = false;
            var mapSize = 16000;
            var maxVision = 400;
            var avatarPos;
            var root;
            var _urlAvatar = "https://d1a370nemizbjq.cloudfront.net/c4630e2f-fa54-4e8a-9537-0aab3e45b76f.glb";
            var _last_position;
            let raycaster;
            const objects = [];
            var userList = [];
            var worldBox = new THREE.Box3();
            const gravityConstant = - 9.8;
            var avatarMass = 60;
            var worldScale = 30;

            Ammo().then( function ( AmmoLib ) {
				Ammo = AmmoLib;
				start();
			});

            function start (){

                tmpTrans = new Ammo.btTransform();
                ammoTmpPos = new Ammo.btVector3();
                ammoTmpQuat = new Ammo.btQuaternion();
                tmpMS = new Ammo.btDefaultMotionState( tmpTrans );

                setupPhysicsWorld();

                setupGraphics();
                // createBlock();
                loadMap();
                // createKinematicBox();

                initEvents();                
            }

            function initBody() {
                return _urlAvatar ? loadEl(_urlAvatar, 1) : false;
            }

            function loadEl(file, num = 0) {
                var mylist = document.getElementById('control');
                var face = 0;
                var all = {}
                loader.load(file, function (gltf) {
                    switch (num) {
                        case 1: {
                            root = gltf.scene;
                            root.name = "Avatar";
                            loadEl('./models/glb/animation.gltf', 2);
                            break;
                        }
                        case 2: {
                            gltfMain = gltf;
                            var body = gltf.scene.getObjectByName('Wolf3D_Avatar');
                            body.parent.remove(body);//elimar skinned mesh
                            
                            animar();
                            break;
                        }
                    }
                });
            }

            function animar() {
                mixers = new THREE.AnimationMixer(root);

                var anima1 = mixers.clipAction(gltfMain.animations[0]).play();
                var anima2 = mixers.clipAction(gltfMain.animations[2]);
                var anima3 = mixers.clipAction(gltfMain.animations[1]);
                var p = false
                if (window.localStorage && window.localStorage.ap) {
                    p = unescape(window.localStorage.ap);
                } else {
                    if(_last_position)
                        p = decodeHtml(_last_position);
                }
                try {
                    p = JSON.parse(p);
                } catch (e) {

                }

                root.position.x = 0//p?p.x:0;
                root.position.z = 0//p?p.z:50;
                root.position.y = 100//p?(p.y>0?p.y:300):450;

                root.userData.animationFPS = 6;
                root.userData.transitionFrames = 15;

                // movement model parameters
                root.userData.maxSpeed = 16 + avatarScale;
                root.userData.maxReverseSpeed = - 105;
                root.userData.frontAcceleration = 600;
                root.userData.backAcceleration = 600;
                root.userData.frontDecceleration = 600;
                root.userData.angularSpeed = 2.5;
                root.userData.speed = 0;
                root.userData.bodyOrientation = p ? p.dir : root.rotation.y;
                root.userData.walkSpeed = root.userData.maxSpeed;
                root.userData.crouchSpeed = root.userData.walkSpeed + 40 + (avatarScale);
                root.userData.animations = [anima1, anima2, anima3];

                root.receiveShadow = false;
                root.castShadow = true;
                root.scale.multiplyScalar(avatarScale)
                scene.add(root);

                gyro = new Gyroscope();
                gyro.add(camera);
                gyro.add(light, light.target);
                root.add(gyro);

                playerCollider = new THREE.Sphere(mixers._root.position, avatarScale);

                addElement(mixers._root);

                animate();
                // connectService();
            }

            function addElement(threeObject){
				scale = {x:threeObject.scale.x, y:threeObject.scale.y+avatarScale, z:threeObject.scale.z}
				let boxGeometry = new THREE.SphereGeometry(scale.x,avatarScale,avatarScale);
				let edges = new THREE.EdgesGeometry(boxGeometry);
				opObject = new THREE.LineSegments( edges, new THREE.LineBasicMaterial({ color: 0x0000ff }));

				opObject.position.set(threeObject.position.x, threeObject.position.y+avatarScale, threeObject.position.z);
				var quat = new THREE.Quaternion()
				threeObject.getWorldQuaternion( quat ); 
				// scene.add(opObject);

				tmpTrans.setIdentity();
				tmpTrans.setOrigin( new Ammo.btVector3( opObject.position.x, opObject.position.y, opObject.position.z ) );
    			tmpTrans.setRotation( new Ammo.btQuaternion( quat.x, quat.y, quat.z, quat.w ) );
                var motionState = new Ammo.btDefaultMotionState( tmpTrans );

				// let colShape = new Ammo.btBoxShape( new Ammo.btVector3( scale.x , scale.y , scale.z  ) );
				let colShape = new Ammo.btSphereShape(scale.x);
    			colShape.setMargin( 0.05 );

				let localInertia = new Ammo.btVector3( 0, 0, 0 );
    			colShape.calculateLocalInertia( avatarMass, localInertia );

				let rbInfo = new Ammo.btRigidBodyConstructionInfo( avatarMass, motionState, colShape, localInertia );
    			let body = new Ammo.btRigidBody( rbInfo );
				body.setFriction(1);
                body.setAngularFactor( 0, 0, 0 );
                body.setActivationState(STATE.DISABLE_DEACTIVATION);

				physicsWorld.addRigidBody( body );

				threeObject.userData.physicsBody = body;
                rigidBodies.push(threeObject);
			}

            function onWindowResize() {
                renderer.setSize( window.innerWidth, window.innerHeight );
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
            }

            function Terrain() {
                // Load the heightmap image
                const textureLoader = new THREE.TextureLoader();
                textureLoader.load( './maps/heightmap.png', function ( heightmap ) {
                    heightmap.wrapS = THREE.RepeatWrapping;
                    heightmap.wrapT = THREE.RepeatWrapping;
                    textureLoader.load( './maps/map.png', function ( texture ) {
                        texture.wrapS = THREE.RepeatWrapping;
                        texture.wrapT = THREE.RepeatWrapping;
                        texture.repeat.set( 1, 1 );
                        var material = new THREE.MeshStandardMaterial( {
                            // color: 0xfff888,
                            // roughness: settings.roughness,
                            // metalness: settings.metalness,

                            map: texture,
                            // normalScale: new THREE.Vector2( 1, - 1 ), // why does the normal map require negation in this case?

                            // aoMap: aoMap,
                            // aoMapIntensity: 1,

                            displacementMap: heightmap,
                            displacementScale: 50,
                            displacementBias: 0, // from original model

                            // envMap: reflectionCube,
                            // envMapIntensity: settings.envMapIntensity,

                            side: THREE.DoubleSide

                        } );
                        material.needsUpdate = true;


                        var geometryTerrain = new THREE.PlaneGeometry(1024, 1024, 256, 256);
                        geometryTerrain.uvsNeedUpdate = true;
                        geometryTerrain.buffersNeedUpdate = true;
                        geometryTerrain.computeVertexNormals();
                        geometryTerrain.attributes.position.needsUpdate = true;
                        geometryTerrain.attributes.normal.needsUpdate = true;

                        terrainMesh = new THREE.Mesh(geometryTerrain, material);
                        terrainMesh.position.set(0, 0, 0);
                        terrainMesh.rotation.x = -Math.PI / 2;
                        terrainMesh.scale.multiplyScalar(1);

                        terrainMesh.material.map = texture;
                        terrainMesh.material.needsUpdate = true;
                        // add the terrain
                        scene.add(terrainMesh);

                        addPlane(terrainMesh,1);
                    }) 
                });
            }

            function loadMap() {
                return Terrain();
                // return loadMap1();
                // Load the heightmap image
                const textureLoader = new THREE.TextureLoader();
                textureLoader.load( './maps/heightmap.png', function ( heightmap ) {
                    heightmap.encoding = THREE.sRGBEncoding;
                    heightmap.wrapS = THREE.RepeatWrapping;
                    heightmap.wrapT = THREE.RepeatWrapping;
                    heightmap.anisotropy = 16;

                    textureLoader.load( './maps/map.png', function ( texture ) {
                        texture.encoding = THREE.sRGBEncoding;
                        texture.wrapS = THREE.RepeatWrapping;
                        texture.wrapT = THREE.RepeatWrapping;
                        texture.anisotropy = 16;

                        var uniforms={
                            bumpTexture: { value: heightmap },
                            bumpScale: { value: 10 },
                            terrainTexture: { value: texture }
                        }

                        var material = new THREE.ShaderMaterial({
                            uniforms:uniforms,
                            vertexShader:vertexShader,
                            fragmentShader:fragmentShader,
                            fog:false,
                            wireframe: false,
                            side: THREE.DoubleSide
                        });

                        var geometryTerrain = new THREE.PlaneGeometry(255, 255, 255, 255);
                        geometryTerrain.computeVertexNormals();
                        geometryTerrain.computeTangents();
                        // console.log(geometryTerrain)
                        var terrainMesh1 = new THREE.Mesh(geometryTerrain, material);
                        

                        const groundMaterial = new THREE.MeshBasicMaterial( { 
                            wireframe: true,
                            map: texture
                         } );
                        groundMaterial.side = THREE.DoubleSide;
                        terrainMesh = new THREE.Mesh( terrainMesh1.geometry, groundMaterial );
                        
                        terrainMesh.position.set(0, 0, 0);
                        terrainMesh.rotation.x = -Math.PI / 2;
                        terrainMesh.receiveShadow = true;
                        terrainMesh.castShadow = true;
                        terrainMesh.scale.multiplyScalar(avatarScale);

                        scene.add(terrainMesh);

                        addPlane(terrainMesh,avatarScale);
                    });
                });
            }

            function addPlane(object,scalingFactor=1){
                var pos = {x: 0, y: 0, z: 0};
                var scale = {x: scalingFactor, y: scalingFactor, z: scalingFactor};
                var quat = {x: 0, y: 0, z: 0, w: 1};
                var mass = 0;

                const quat1 = new THREE.Quaternion();
				var transform = new Ammo.btTransform();
                transform.setIdentity();
                transform.setOrigin( new Ammo.btVector3( pos.x, pos.y, pos.z ) );
                transform.setRotation( new Ammo.btQuaternion( quat1.x, quat1.y, quat1.z, quat1.w ) );
                var motionState = new Ammo.btDefaultMotionState( transform );

				let triangles = [];

				const mesh = new Ammo.btTriangleMesh(true, true);
                // console.log(object.geometry.attributes.position.array)
                var vertexPositionArray = object.geometry.attributes.position.array;
				for (let i = 0; i < object.geometry.attributes.position.count/3; i++) {
					mesh.addTriangle(
						new Ammo.btVector3(vertexPositionArray[i*9+0]*scalingFactor, vertexPositionArray[i*9+1]*scalingFactor, vertexPositionArray[i*9+2]*scalingFactor ),
                        new Ammo.btVector3(vertexPositionArray[i*9+3]*scalingFactor, vertexPositionArray[i*9+4]*scalingFactor, vertexPositionArray[i*9+5]*scalingFactor),
                        new Ammo.btVector3(vertexPositionArray[i*9+6]*scalingFactor, vertexPositionArray[i*9+7]*scalingFactor, vertexPositionArray[i*9+8]*scalingFactor),
                        false
					);
				}
				// console.log(vertexPositionArray)
				const colShape = new Ammo.btBvhTriangleMeshShape(mesh,true,true);
                colShape.setMargin( 0.05 );
				var localInertia = new Ammo.btVector3( 0, 0, 0 );
                colShape.calculateLocalInertia( mass, localInertia );

				const rbInfo = new Ammo.btRigidBodyConstructionInfo(mass, motionState, colShape, localInertia);
				const body = new Ammo.btRigidBody(rbInfo);
                // console.log(body)
                // body.setFriction(10000000);
                // body.setRollingFriction(0);
				physicsWorld.addRigidBody(body);
                initBody();
                // 
			}

            function setupPhysicsWorld(){
                const collisionConfiguration = new Ammo.btSoftBodyRigidBodyCollisionConfiguration();
                const dispatcher = new Ammo.btCollisionDispatcher( collisionConfiguration );
                const broadphase = new Ammo.btDbvtBroadphase();
                const solver = new Ammo.btSequentialImpulseConstraintSolver();
                const softBodySolver = new Ammo.btDefaultSoftBodySolver();
                physicsWorld = new Ammo.btSoftRigidDynamicsWorld( dispatcher, broadphase, solver, collisionConfiguration, softBodySolver );
                physicsWorld.setGravity( new Ammo.btVector3( 0, gravityConstant, 0 ) );
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
				// cameraControls.enableZoom = true;
                cameraControls.update();

                raycaster = new THREE.Raycaster();
                
                initEvents();
            }

            //EVENT HANDLERS
            function initEvents() {
                window.addEventListener('resize', onWindowResize);
                document.addEventListener('pointermove', event => {
                    if (event.buttons && mixers._root) {
                        var angle = getNewPointOnVector(cameraControls.object.position, mixers._root.position);
                        camera.lookAt(angle.x, angle.y, angle.z);
                    }
                });

                document.addEventListener('wheel', () => {
                    if (!mixers._root)
                        return;
                    var angle = getNewPointOnVector(cameraControls.target, mixers._root.position);
                    camera.lookAt(angle.x, angle.y, angle.z);
                });

                document.addEventListener('keydown', onKeyDown);
                document.addEventListener('keyup', onKeyUp);
            }
            
            function onKeyDown(event) {
                switch (event.code) {

                    case 'ArrowUp':
                    case 'KeyW': 
                        controlsAll.moveForward = true;                        
                        moveDirection.forward = 1; 
                        break;
                    case 'KeyE':
                        controlsAll.moveForward = true;
                        controlsAll.crouch = true;
                        moveDirection.forward = 1;
                        break;

                    case 'ArrowDown':
                    case 'KeyS':
                        controlsAll.moveBackward = true;
                        controlsAll.goinBack = true;
                        moveDirection.back = 1;
                        break;

                    case 'ArrowLeft':
                    case 'KeyA': 
                        controlsAll.moveLeft = true; 
                        moveDirection.left = 1
                        break;

                    case 'ArrowRight':
                    case 'KeyD': 
                        controlsAll.moveRight = true; 
                        moveDirection.right = 1
                        break;

                    case 'KeyC': 
                        controlsAll.crouch = true; 
                        break;
                    case 'Space':
                         controlsAll.jump = true; 
                         break;
                    case 'ControlLeft':
                    case 'ControlRight': 
                        controlsAll.attack = true; 
                        break;

                }
                avatarMoving = controlsAll.goinBack || controlsAll.moveBackward || controlsAll.moveForward || controlsAll.moveLeft || controlsAll.moveRight;
                // intersections();
            }

            function onKeyUp(event) {
                var isControlKey = false;
                switch (event.code) {
                    case 'ArrowUp':
                    case 'KeyW':
                        controlsAll.moveForward = false;
                        isControlKey = true;
                        moveDirection.forward = 0
                        break;

                    case 'KeyE':
                        controlsAll.moveForward = false;
                        controlsAll.crouch = false;
                        isControlKey = true;
                        moveDirection.forward = 0
                        break;

                    case 'ArrowDown':
                    case 'KeyS':
                        controlsAll.moveBackward = false;
                        controlsAll.goinBack = false;
                        isControlKey = true;                        
                        moveDirection.back = 0;
                        break;

                    case 'ArrowLeft':
                    case 'KeyA':
                        controlsAll.moveLeft = false;
                        isControlKey = true;
                        moveDirection.left = 0;
                        break;

                    case 'ArrowRight':
                    case 'KeyD':
                        controlsAll.moveRight = false;
                        isControlKey = true;
                        moveDirection.right = 0;
                        break;

                    case 'KeyC':
                        controlsAll.crouch = false;
                        isControlKey = true; break;
                    case 'Space':
                        controlsAll.jump = false;
                        isControlKey = true;
                        break;
                    case 'ControlLeft':
                    case 'ControlRight':
                        controlsAll.attack = false;
                        isControlKey = true;
                        break;

                }
                if (isControlKey) {
                    // sendPosition(clock.getDelta());
                }
                avatarMoving = false;
            }

            const sendPosition = (delta) => {
                var mouseP = {
                    x: mixers._root.position.x,
                    y: mixers._root.position.y,
                    z: mixers._root.position.z,
                    dir: mixers._root.rotation.y,
                    // uid: _userLogin,
                    op: 'up',
                    control: controlsAll,
                    delta: delta,
                    link: _urlAvatar,
                    d: distaXY()
                };
                window.localStorage.ap = escape(JSON.stringify(mouseP));
                if (auxWs.readyState != 1) {
                    return;
                }
                if(avatarMoving)
                    auxWs.send(JSON.stringify(mouseP));
            }

            function distaXY() {
                var px = mapSize / 2 + mixers._root.position.x;
                var py = mapSize / 2 + mixers._root.position.z;

                var cx = parseInt(px / maxVision);
                var cy = parseInt(py / maxVision);
                avatarPos = [cx, cy];
                return avatarPos;
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
                render();
                renderer.render(scene, camera);
            }

            function render() {
                var delta = clock.getDelta();

                if (mixers) {
                    mixers.update(delta);
                    updateMovementModel(delta, mixers, controlsAll);
                    moveAvatar();
                }

                updatePhysics( delta );

                if (playerCollider) {
                    updatePlayer(delta);
                    // setLimitWorld(worldBox);
                }
                
                var us = Object.values(userList);
                for (var i = 0; i < us.length; i++) {
                    if (us[i] != "w" && us[i]) {
                        us[i].update(delta);
                    }
                }
            }

            function setLimitWorld(box) {
                if (mixers._root.position.x > box.max.x) {
                    mixers._root.position.x = box.max.x;
                }

                if (mixers._root.position.x < box.min.x) {
                    mixers._root.position.x = box.min.x;
                }

                if (mixers._root.position.z > box.max.z) {
                    mixers._root.position.z = box.max.z;
                }

                if (mixers._root.position.z < box.min.z) {
                    mixers._root.position.z = box.min.z;
                }
            }

            function updatePlayer(deltaTime) {
                playerCollider.set(mixers._root.position, playerCollider.radius);
                // raycaster.setFromCamera( character.root.position, camera );

                raycaster.ray.origin.copy(mixers._root.position);
                var dir = new THREE.Vector3();
                mixers._root.getWorldDirection(dir);
                dir.multiplyScalar(-1);
                raycaster.ray.direction.copy(dir);
                raycaster.far = 200;

                const intersects = raycaster.intersectObjects(objects);
                if (!intersects.length) {
                    characterLastPosition.copy(mixers._root.position);
                } else {
                    mixers._root.position.copy(characterLastPosition);
                }

                if (mixers._root.userData.speed != 0 || avatarMoving) {
                    // sendPosition(deltaTime);
                }
            }

            function updateMovementModel(delta, el, controls) {
                function exponentialEaseOut(k) {
                    return k === 1 ? 1 : - Math.pow(2, - 10 * k) + 1;
                }
                var obj = el._root.userData;
                // speed based on controls
                var runWalk = controls.crouch ? 2 : 1;
                if (controls.crouch) obj.maxSpeed = obj.crouchSpeed;
                else obj.maxSpeed = obj.walkSpeed;
                obj.maxReverseSpeed = - obj.maxSpeed;

                if (controls.moveForward) obj.speed = THREE.MathUtils.clamp(obj.speed + delta * obj.frontAcceleration, obj.maxReverseSpeed, obj.maxSpeed);
                else if (controls.moveBackward) obj.speed = THREE.MathUtils.clamp(obj.speed - delta * obj.backAcceleration, obj.maxReverseSpeed, obj.maxSpeed);
                else obj.speed = 0;
                // orientation based on controls
                // (don't just stand while turning)
                const dir = controls.goinBack ? - 1 : 1;
                if (controls.moveLeft) {
                    obj.bodyOrientation += delta * obj.angularSpeed;
                    obj.speed = THREE.MathUtils.clamp(obj.speed + dir * delta * obj.frontAcceleration, obj.maxReverseSpeed, obj.maxSpeed);
                }
                if (controls.moveRight) {
                    obj.bodyOrientation -= delta * obj.angularSpeed;
                    obj.speed = THREE.MathUtils.clamp(obj.speed + dir * delta * obj.frontAcceleration, obj.maxReverseSpeed, obj.maxSpeed);
                }
                // speed decay
                if (!(controls.moveForward || controls.moveBackward)) {
                    if (obj.speed > 0) {
                        const k = exponentialEaseOut(obj.speed / obj.maxSpeed);
                        obj.speed = THREE.MathUtils.clamp(obj.speed - k * delta * obj.frontDecceleration, 0, obj.maxSpeed);
                    } else {
                        const k = exponentialEaseOut(obj.speed / obj.maxReverseSpeed);
                        obj.speed = THREE.MathUtils.clamp(obj.speed + k * delta * obj.backAcceleration, obj.maxReverseSpeed, 0);
                    }
                }
                // displacement
                const forwardDelta = obj.speed * delta;
                el._root.position.x += Math.sin(obj.bodyOrientation) * forwardDelta;
                el._root.position.z += Math.cos(obj.bodyOrientation) * forwardDelta;
                if (obj.speed != 0) {
                    obj.animations[runWalk].timeScale = dir;
                    if (!obj.animations[runWalk].isRunning()) {
                        obj.animations[0].stop();
                    }
                    if (runWalk == 2) {
                        obj.animations[2].play();
                    } else {
                        obj.animations[1].play();
                    }
                } else {
                    if (!obj.animations[0].isRunning() && !(controls.moveLeft || controls.moveRight)) {
                        obj.animations[0].play();
                    }

                    if (controls.moveLeft || controls.moveRight) {
                        obj.animations[0].stop();
                        obj.animations[2].stop();
                        obj.animations[1].timeScale = dir;
                        obj.animations[1].play();
                    } else {
                        obj.animations[2].stop();
                        obj.animations[1].stop();
                    }
                }
                el._root.rotation.y = obj.bodyOrientation;
            }

            function moveAvatar(){
                if(mixers._root && mixers._root.userData.physicsBody){
                    
                    opObject.rotation.y = mixers._root.rotation.y;
                    opObject.position.x = mixers._root.position.x;
                    opObject.position.y = mixers._root.position.y+avatarScale;
                    opObject.position.z = mixers._root.position.z;
                    physicsBody = mixers._root.userData.physicsBody;

                    var pos = mixers._root.position;
                    var ammoTmpPos1 = ammoTmpPos;
                    ammoTmpPos.setValue(pos.x, pos.y, pos.z);
                    physicsBody.getWorldTransform().setOrigin(ammoTmpPos);
                    var gr = gravityConstant*avatarMass*100;
                    ammoTmpPos1.setValue(0, gr, 0);
                    // physicsBody.applyForce(ammoTmpPos1, ammoTmpPos);
                }
            }

            function updatePhysics( deltaTime ){

                // Step world
                // physicsWorld.stepSimulation( deltaTime, 10 );
                physicsWorld.stepSimulation(deltaTime, 100, 1 / 240.0);
                // Update rigid bodies
                for ( var i = 0; i < rigidBodies.length; i++ ) {
                    var objThree = rigidBodies[ i ];
                    var objAmmo = objThree.userData.physicsBody;
                    var ms = objAmmo.getMotionState();
                    if ( ms ) {

                        ms.getWorldTransform( tmpTrans );
                        var p = tmpTrans.getOrigin();
                        // var q = tmpTrans.getRotation();
                        avatarMoving = p.x() !=objThree.position.x || p.y() != objThree.position.y || p.z() != objThree.position.z;
                        objThree.position.set( p.x(), p.y(), p.z() );
                        if(objThree.position.y<-100){
                            objThree.position.y = 100;
                            // objThree.position.x = 0;
                            // objThree.position.z = 0;
                        }
                        if(avatarMoving)
                            sendPosition(deltaTime);
                        // objThree.quaternion.set( q.x(), q.y(), q.z(), q.w() );

                    }
                }

            }

            async function connectService() {
                //var host = 'ws://127.0.0.1:12345/ws.php';
                var host = 'ws://localhost:12346/';
                var _ws = new WebSocket(host);

                _ws.onclose = () => {
                    connectService();
                }

                _ws.onmessage = async function (evt) {
                    var msg = JSON.parse(evt.data);
                    if (msg) {
                        switch (msg['op']) {
                            case 'up': {
                                var id = msg['uid'];

                                var el = userList[id];
                                if (!el) {
                                    userList[id] = "w";
                                    addUser(msg);
                                    wait(msg);
                                } else {
                                    await moveUser(msg);
                                }

                                break;
                            }
                            case 'disc': {
                                var id = msg['uid'];
                                if (userList[id]) {
                                    userList[id]._root.userData.disconected = true;
                                }
                                break;
                            }
                        }
                    }
                };

                _ws.onopen = function (e) {
                    var delta = clock.getDelta();
                    updateMovementModel(delta, mixers, controlsAll)
                    // sendPosition(delta);
                };

                auxWs = _ws;
            }

            // function loadMap1(){
			// 	var OBJFile = './models/obj/terrain.obj';
			// 	var MTLFile = './models/obj/terrain.mtl';
			// 	var JPGFile = './models/obj/terrain.png';
			// 	var worldScale = 100;
            //     new OBJLoader().load(OBJFile, function (object) {
            //         const groundMaterial = new THREE.MeshPhongMaterial( { color: 0xC7C7C7,side:THREE.DoubleSide } );
            //         groundMaterial.side = THREE.DoubleSide;

            //         terrainMesh = new THREE.Mesh( object.children[0].geometry, groundMaterial );
            //         terrainMesh.receiveShadow = true;
            //         terrainMesh.castShadow = true;
            //         terrainMesh.scale.multiplyScalar(worldScale);
            //         terrainMesh.position.y = 0;
            //         const textureLoader = new THREE.TextureLoader();
            //         textureLoader.load( './vendor/mrdoob/three.js/examples/textures/terrain/grasslight-big.jpg', function ( texture ) {
            //         // textureLoader.load( './vendor/mrdoob/three.js/examples/textures/minecraft/grass_dirt.png', function ( texture ) {
            //             texture.wrapS = THREE.RepeatWrapping;
            //             texture.wrapT = THREE.RepeatWrapping;
            //             texture.repeat.set( 0.5, 0.5 );
            //             groundMaterial.map = texture;
            //             groundMaterial.needsUpdate = true;
            //         } );

            //         scene.add( terrainMesh );

            //         // add plane to phys
            //         addPlane(terrainMesh,worldScale);
            //     });
			// }

            // function initAvatar1(){
			// 	loader.load( 'https://d1a370nemizbjq.cloudfront.net/c4630e2f-fa54-4e8a-9537-0aab3e45b76f.glb', function ( gltf ) {
			// 		gltf.scene.traverse( function ( object ) {
			// 			if ( object.isMesh ) {
			// 				object.castShadow = true;
			// 				// console.log(object)
			// 				if (object.morphTargetDictionary){
			// 					// console.log("sss")
			// 					object.morphTargetDictionary.mouthSmile = 0;
			// 				} 
			// 			}
			// 		} );

			// 		loader.load( './models/glb/animation.gltf', function ( gltf1 ) {
			// 			gltfMain = gltf1;
			// 			const model1 = SkeletonUtils.clone( gltf.scene );
			// 			// return initAvatar();
			// 			mixers = new THREE.AnimationMixer( model1 );

			// 			// mixers._actions = gltf.animations;
			// 			// mixers.clipAction( mixers._actions[0]).play(); // idle
			// 			var anima1 = mixers.clipAction( gltfMain.animations[0]).play();
			// 			var anima2 = mixers.clipAction( gltfMain.animations[2]);
			// 			var anima3 = mixers.clipAction( gltfMain.animations[1]);
			// 			model1.position.x = 10;
			// 			model1.position.z = 50;
			// 			model1.position.y = 50;

			// 			// model1.rotation.y = -3;
			// 			model1.userData.animationFPS = 160;
			// 			model1.userData.transitionFrames = 150;

			// 			// movement model parameters
			// 			model1.userData.maxSpeed = 250;
			// 			model1.userData.maxReverseSpeed = - 105;
			// 			model1.userData.frontAcceleration = 600;
			// 			model1.userData.backAcceleration = 600;
			// 			model1.userData.frontDecceleration = 600;
			// 			model1.userData.angularSpeed = 2.5;
			// 			model1.userData.speed = 0;
			// 			model1.userData.bodyOrientation = model1.rotation.y;
			// 			model1.userData.walkSpeed = 50;
			// 			model1.userData.crouchSpeed = model1.userData.maxSpeed;
			// 			model1.userData.animations = [anima1,anima2,anima3];
			// 			// model1.userData.animations = [anima1,anima3];

			// 			model1.scale.multiplyScalar(avatarScale);//(100,100,100);
			// 			scene.add( model1 );
						
			// 			gyro = new Gyroscope();
			// 			gyro.add( camera );
			// 			gyro.add( light, light.target );
			// 			model1.add( gyro );

			// 			var radius = 50;
			// 			var height = 50;
			// 			playerCollider = new THREE.Sphere(mixers._root.position, radius );
			// 			characterLastPosition.copy(mixers._root.position);

			// 			addElement(model1);
			// 		});			

			// 		// initAvatar();
			// 		// connectService();
			// 	} );
			// }

            // function createBall(){
                
            //     var pos = {x: 0, y: 40, z: 0};
            //     var radius = 2;
            //     var quat = {x: 0, y: 0, z: 0, w: 1};
            //     var mass = 1;

            //     //threeJS Section
            //     var ball = ballObject = new THREE.Mesh(new THREE.SphereBufferGeometry(radius), new THREE.MeshPhongMaterial({color: 0xff0505}));

            //     ball.position.set(pos.x, pos.y, pos.z);
                
            //     ball.castShadow = true;
            //     ball.receiveShadow = true;
            //     // ball.scale.multiplyScalar(30);
            //     // scene.add(ball);
            //     // gyro = new Gyroscope();
            //     // gyro.add( camera );
            //     // gyro.add( light, light.target );
            //     // ballObject.add( gyro );
            //     // mixers._root = ballObject;

            //     //Ammojs Section
            //     // var transform = new Ammo.btTransform();
            //     // transform.setIdentity();
            //     // transform.setOrigin( new Ammo.btVector3( pos.x, pos.y, pos.z ) );
            //     // transform.setRotation( new Ammo.btQuaternion( quat.x, quat.y, quat.z, quat.w ) );
            //     // var motionState = new Ammo.btDefaultMotionState( transform );

            //     // var colShape = new Ammo.btSphereShape( radius );
            //     // colShape.setMargin( 0.05 );

            //     var localInertia = new Ammo.btVector3( 0, physicsWorld.getGravity().y(), 0 );
            //     colShape.calculateLocalInertia( mass, localInertia );

            //     var rbInfo = new Ammo.btRigidBodyConstructionInfo( mass, motionState, colShape, localInertia );
            //     var body = new Ammo.btRigidBody( rbInfo );

            //     body.setFriction(4);
            //     body.setRollingFriction(4);

            //     body.setActivationState( STATE.DISABLE_DEACTIVATION )


            //     physicsWorld.addRigidBody( body );
                
            //     ball.userData.physicsBody = body;
            //     rigidBodies.push(ball);
            // }

             // function renderFrame(){
            //     var delta  = clock.getDelta();
            //     moveAvatar();
            //     updatePhysics( delta );
            //     renderer.render( scene, camera );
            //     requestAnimationFrame( renderFrame );

            //     if (mixers) {
            //         mixers.update(delta);
            //         updateMovementModel(delta, mixers, controlsAll);
            //     }
            // }

            // function handleKeyDown(event){

            //     var keyCode = event.keyCode;

            //     switch(keyCode){

            //         case 87: //W: FORWARD
            //             moveDirection.forward = 1
            //             break;
                        
            //         case 83: //S: BACK
            //             moveDirection.back = 1
            //             break;
                        
            //         case 65: //A: LEFT
            //             moveDirection.left = 1
            //             break;
                        
            //         case 68: //D: RIGHT
            //             moveDirection.right = 1
            //             break;

            //         case 38: //↑: FORWARD
            //             kMoveDirection.forward = 1
            //             break;
                        
            //         case 40: //↓: BACK
            //             kMoveDirection.back = 1
            //             break;
                        
            //         case 37: //←: LEFT
            //             kMoveDirection.left = 1
            //             break;
                        
            //         case 39: //→: RIGHT
            //             kMoveDirection.right = 1
            //             break;
                        
            //     }
            // }
            
            
            // function handleKeyUp(event){
            //     var keyCode = event.keyCode;

            //     switch(keyCode){
            //         case 87: //FORWARD
            //             moveDirection.forward = 0
            //             break;
                        
            //         case 83: //BACK
            //             moveDirection.back = 0
            //             break;
                        
            //         case 65: //LEFT
            //             moveDirection.left = 0
            //             break;
                        
            //         case 68: //RIGHT
            //             moveDirection.right = 0
            //             break;

            //         case 38: //↑: FORWARD
            //             kMoveDirection.forward = 0
            //             break;
                        
            //         case 40: //↓: BACK
            //             kMoveDirection.back = 0
            //             break;
                        
            //         case 37: //←: LEFT
            //             kMoveDirection.left = 0
            //             break;
                        
            //         case 39: //→: RIGHT
            //             kMoveDirection.right = 0
            //             break;
            //     }

            // }

        </script>
    
</body></html>