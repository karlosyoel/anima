<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <title>Game</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <style>
        body { margin: 0; }
    </style>
</head>
<body>

<div id="main_map">

</div>
<script type="importmap">
    {
        "imports": {
            "three": "./vendor/mrdoob/three.js/build/three.module.js"
        }
    }
</script>
<script async src="./otros/es-module-shims.js"></script>

<script type="module">    
    import * as THREE from "three"
    import { GLTFExporter } from './vendor/mrdoob/three.js/examples/jsm/exporters/GLTFExporter.js';
    import { OBJExporter } from './vendor/mrdoob/three.js/examples/jsm/exporters/OBJExporter.js';
    import { fragmentShader,vertexShader } from './otros/ShaderTerrain.js';
    import { OrbitControls } from './vendor/mrdoob/three.js/examples/jsm/controls/OrbitControls.js';
    // console.log(terrain)

    var cameraControls;
    var scene = {};
    var renderer = {};
    var camera = {};
    var terrain,material,container;


    var n = 0;

    init();

    function init(){  
        scene = new THREE.Scene();
        scene.background = new THREE.Color( 0xffffff );
        camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 1, 100000);

        camera.position.set(0, 1, 45);
        scene.add(camera)
        camera.lookAt(scene.position);

        container = document.createElement( 'div' );
        document.body.appendChild( container );


        renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setPixelRatio(window.devicePixelRatio);
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.shadowMapEnabled = true;

        container.appendChild(renderer.domElement);

        var hemiLight = new THREE.HemisphereLight( 0xffffff, 0xffffff, 1 );
        hemiLight.color.setHSL( 0.6, 0.6, 0.6 );
        hemiLight.groundColor.setHSL( 0.1, 1, 0.4 );
        hemiLight.position.set( 0, 50, 0 );
        scene.add( hemiLight );

        // LIGHTS
        scene.add( new THREE.AmbientLight( 0x222222 ) );

        var light = new THREE.DirectionalLight( 0xffffff, 0.25 );
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

        renderer.outputEncoding = THREE.sRGBEncoding;
        renderer.shadowMap.enabled = true;
        cameraControls = new OrbitControls( camera, renderer.domElement );
        // cameraControls.enableZoom = true;
        cameraControls.update();
        // initMap();
        
        Terrain();
        render();
        // tell everything is ready
        
        // saveFile()

        window.addEventListener('resize', onWindowResize);
    }

    function onWindowResize() {
        renderer.setSize( window.innerWidth, window.innerHeight );
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
    }

    function loaded() {
        n++;
        console.log("loaded: " + n);

        if (n == 3) {
            terrain.visible = true;            
            render();            
            saveFile();
        }
    }

    function Terrain() {
        // Load the heightmap image
        const heightMap = THREE.ImageUtils.loadTexture('maps/heightmap.png', null, loaded);
        // Apply some properties to ensure it renders correctly
        heightMap.encoding = THREE.sRGBEncoding;
        heightMap.wrapS = THREE.RepeatWrapping;
        heightMap.wrapT = THREE.RepeatWrapping;
        heightMap.anisotropy = 16;

        // Load the texture map
        // const textureMap = THREE.ImageUtils.loadTexture('maps/texturemap1024.png', null, loaded);
        const textureMap = THREE.ImageUtils.loadTexture('maps/map.png', null, loaded);
        // Apply some properties to ensure it renders correctly
        textureMap.encoding = THREE.sRGBEncoding;
        textureMap.wrapS = THREE.RepeatWrapping;
        textureMap.wrapT = THREE.RepeatWrapping;
        textureMap.anisotropy = 16;


        var uniforms={
            // Feed the heightmap
            bumpTexture: { value: heightMap },
            // Feed the scaling constant for the heightmap
            bumpScale: { value: 50 },
            // Feed the texture map
            terrainTexture: { value: textureMap }
        }

        material = new THREE.ShaderMaterial({
            uniforms:uniforms,
            vertexShader:vertexShader,
            fragmentShader:fragmentShader,
            fog:false,
            wireframe: false,
            side: THREE.DoubleSide
        });
        // Feed the shaders as strings

        var geometryTerrain = new THREE.PlaneGeometry(1024, 1024, 256, 256);
        terrain = new THREE.Mesh(geometryTerrain, material);
        terrain.position.set(0, 0, 0);
        terrain.rotation.x = -Math.PI / 2;
        // add the terrain
        scene.add(terrain);

        loaded();
    }

    function render() {
        requestAnimationFrame(render);
        renderer.render(scene, camera);
    }

    function saveFile() {
        const exporter = new OBJExporter();
        var ex = exporter.parse(scene);
        saveArrayBuffer(ex, 'scene.obj');
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

</script>
</body>
</html>
