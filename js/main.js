import * as THREE from 'three';
// three = THREE;
// import Stats from '../vendor/mrdoob/three.js/examples/jsm/libs/stats.module.js';

import { FlyControls } from '../vendor/mrdoob/three.js/examples/jsm/controls/FlyControls.js';
// import { OrbitControls } from '../three.js/examples/jsm/controls/OrbitControls.js';
// import { FirstPersonControls } from '../three.js/examples/jsm/controls/FirstPersonControls.js';

import { GLTFLoader } from '../vendor/mrdoob/three.js/examples/jsm/loaders/GLTFLoader.js';
// import { Lensflare, LensflareElement } from './three.js/examples/jsm/objects/Lensflare.js';

let stats,
renderer,
scene,
camera,
sphereBg,
nucleus,
stars,
controls,
controls1,
container,
timeout_Debounce,
noise = new SimplexNoise(),
cameraSpeed = 0,
blobScale = 0;
let mousePos = [0,0];
let currnetMouseX,currnetMouseY;

var target = new THREE.Vector3();

var mouseX = 0, mouseY = 0;

var windowHalfX = window.innerWidth / 2;
var windowHalfY = window.innerHeight / 2;

const clock = new THREE.Clock();
var pos3d = new THREE.Vector3();
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector3();

var planetas = [];
var palanetasAnimar = [];
var galaxiasAnimar = [];
var speedAll = 100;
var touchEvent = false;
var touchD = 0;

init();
animate();

function init() {
    container = document.getElementById("canvas_container");
    windowHalfX = window.innerWidth / 2;
    windowHalfY = window.innerHeight / 2;
    // camera
    // camera = new THREE.PerspectiveCamera( 55, window.innerWidth / window.innerHeight, 1, 15000 );
    // camera.position.z = 250;
    camera = new THREE.PerspectiveCamera(55, window.innerWidth / window.innerHeight, 1, 15000)
    camera.position.z = 2;
    
    // scene

    scene = new THREE.Scene();
    // scene.background = new THREE.Color().setHSL( 0.51, 0.4, 0.01 );
    // scene.fog = new THREE.Fog( scene.background, 3500, 15000 );

    const directionalLight = new THREE.DirectionalLight("#fff", 2);
    directionalLight.position.set(0, 50, -20);
    scene.add(directionalLight);

    let ambientLight = new THREE.AmbientLight("#ffffff", 1);
    ambientLight.position.set(0, 20, 20);
    scene.add(ambientLight);


    renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: true
    });
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);
    // world
    const loader = new THREE.TextureLoader();

    // const s = 250;

    // const geometry1 = new THREE.MeshLambertMaterial({map:material} );
    // const material = new THREE.MeshPhongMaterial( { color: 0xffffff, specular: 0xffffff, shininess: 50 } );
    // const material = loader.load("https://i.ibb.co/yYS2yx5/p3-ttfn70.png");
    // var geometry1 = new THREE.PlaneGeometry(100, 100);
    // var material = new THREE.MeshLambertMaterial({
    // 	map: loader.load('https://i.ibb.co/yYS2yx5/p3-ttfn70.png'),
    // 	transparent: true,
    // 	alphaTest: 0
    // });
    // for ( let i = 0; i < 3000; i ++ ) {

    // 	const mesh = new THREE.Mesh( geometry1, material );

    // 	mesh.position.x = 8000 * ( 2.0 * Math.random() - 1.0 );
    // 	mesh.position.y = 8000 * ( 2.0 * Math.random() - 1.0 );
    // 	mesh.position.z = 8000 * ( 2.0 * Math.random() - 1.0 );

    // 	mesh.rotation.x = Math.random() * Math.PI;
    // 	mesh.rotation.y = Math.random() * Math.PI;
    // 	mesh.rotation.z = Math.random() * Math.PI;

    // 	mesh.matrixAutoUpdate = false;
    // 	mesh.updateMatrix();

    // 	scene.add( mesh );

    // }

    // const loader = new THREE.TextureLoader();
    const textureSphereBg = loader.load('./imgs/bg3-je3ddz.jpg');
    const texturenucleus1 = loader.load('./imgs/ee7eb380d570674fb1a47c326c8687b2.jpg');
    const texturenucleus = loader.load('./imgs/earth.jpeg');
    const textureStar = loader.load("./imgs/p1-g3zb2a.png");
    const texture1 = loader.load("./imgs/p2-b3gnym.png");  
    const texture2 = loader.load("./imgs/p3-ttfn70.png");
    const galaxias = loader.load("./imgs/p4-avirap.png");

    planetas.push(loader.load("./imgs/1280px-Mars_Viking_MDIM21_1km_plus_poles.jpg"));
    planetas.push(loader.load("./imgs/big_d28026d0bc.jpg"));
    planetas.push(loader.load("./imgs/earth.jpeg"));
    planetas.push(loader.load("./imgs/ee7eb380d570674fb1a47c326c8687b2.jpg"));
    planetas.push(loader.load("./imgs/jupiter-planet-texture-background-elements-260nw-1549145969.jpg"));
    planetas.push(loader.load("./imgs/mars0_src_smaller.jpg"));
    planetas.push(loader.load("./imgs/Rammed-Earth-Works-Stadium-TechCenter-8543.jpg"));
    planetas.push(loader.load("./imgs/star-nc8wkw.jpg"));
    // console.log(planetas[0])
    // planetas.push(loader.load("./imgs/texture-surface-jupiter-elements-this-260nw-1332420641.jpg"));
    // planetas.push(loader.load("./imgs/textured-surface-planet-jupiter-closeuptexture-260nw-1701426835.jpg"));
    // planetas.push(loader.load("./imgs/surface-mars-craters-impact-on-260nw-2043301529.jpg"));
    // planetas.push(loader.load("./imgs/moon-surface-seamless-texture-background-260nw-1827119627.jpg"));

    /*  Nucleus  */   
    texturenucleus.anisotropy = 16;
    let icosahedronGeometry = new THREE.IcosahedronGeometry(0.3, 10);
    let lambertMaterial = new THREE.MeshPhongMaterial({ map: texturenucleus });
    nucleus = new THREE.Mesh(icosahedronGeometry, lambertMaterial);
    scene.add(nucleus);

    let lambertMaterial1 = new THREE.MeshPhongMaterial({ map: texturenucleus1 });
    let icosahedronGeometry1 = new THREE.IcosahedronGeometry(0.1, 10);
    var nucleus1 = new THREE.Mesh(icosahedronGeometry1, lambertMaterial1);
    nucleus1.position.setX(0.3);
    nucleus1.position.setY(0.5);
    scene.add(nucleus1);

    const loaderGLTFL = new GLTFLoader();

    loaderGLTFL.load( './ship/space craft.glb', function ( gltf ) {
        gltf.scene.position.x = 10;
        gltf.scene.scale.set(0.001,0.001,0.001);
        scene.add( gltf.scene );

    }, undefined, function ( error ) {

        console.error( error );

    } );

    // lights

    // const dirLight = new THREE.DirectionalLight( 0xffffff, 0.05 );
    // dirLight.position.set( 0, - 1, 0 ).normalize();
    // dirLight.color.setHSL( 0.1, 0.7, 0.5 );
    // scene.add( dirLight );

    // lensflares
    // const textureLoader = new THREE.TextureLoader();

    // const textureFlare0 = textureLoader.load( 'textures/lensflare/lensflare0.png' );
    // const textureFlare3 = textureLoader.load( 'textures/lensflare/lensflare3.png' );

    // addLight( 0.55, 0.9, 0.5, 5000, 0, - 1000 );
    // addLight( 0.08, 0.8, 0.5, 0, 0, - 1000 );
    // addLight( 0.995, 0.5, 0.9, 5000, 5000, - 1000 );

    // function addLight( h, s, l, x, y, z ) {

    // 	const light = new THREE.PointLight( 0xffffff, 1.5, 2000 );
    // 	light.color.setHSL( h, s, l );
    // 	light.position.set( x, y, z );
    // 	scene.add( light );

    // 	const lensflare = new Lensflare();
    // 	lensflare.addElement( new LensflareElement( textureFlare0, 700, 0, light.color ) );
    // 	lensflare.addElement( new LensflareElement( textureFlare3, 60, 0.6 ) );
    // 	lensflare.addElement( new LensflareElement( textureFlare3, 70, 0.7 ) );
    // 	lensflare.addElement( new LensflareElement( textureFlare3, 120, 0.9 ) );
    // 	lensflare.addElement( new LensflareElement( textureFlare3, 70, 1 ) );
    // 	light.add( lensflare );

    // }

    // renderer

    // renderer = new THREE.WebGLRenderer( { antialias: true } );
    // renderer.setPixelRatio( window.devicePixelRatio );
    // renderer.setSize( window.innerWidth, window.innerHeight );
    // renderer.outputEncoding = THREE.sRGBEncoding;
    // container.appendChild( renderer.domElement );

    

    //

    /*    Sphere  Background   */
    // textureSphereBg.anisotropy = 16;
    // let geometrySphereBg = new THREE.SphereBufferGeometry(150, 40, 40);

    // let materialSphereBg = new THREE.MeshBasicMaterial({
    // 	side: THREE.BackSide,
    // 	map: textureSphereBg,
    // });
    // sphereBg = new THREE.Mesh(geometrySphereBg, materialSphereBg);
    // scene.add(sphereBg);


    renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: true
    });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);
    let starsGeometry = new THREE.PlaneGeometry();

// for (let i = 0; i < 50; i++) {
//     let particleStar = randomPointSphere(150); 

//     particleStar.velocity = THREE.MathUtils.randInt(50, 200);

//     particleStar.startX = particleStar.x;
//     particleStar.startY = particleStar.y;
//     particleStar.startZ = particleStar.z;

//     starsGeometry.vertices.push(particleStar);
// }

    // let starsMaterial = new THREE.PointsMaterial({
    // 	size: 5,
    // 	color: "#ffffff",
    // 	transparent: true,
    // 	opacity: 0.8,
    // 	map: textureStar,
    // 	blending: THREE.AdditiveBlending,
    // });
    // starsMaterial.depthWrite = false;  
    // stars = new THREE.Points(starsGeometry, starsMaterial);
    // scene.add(stars);

    /*    Fixed Stars   */
    
    function createPlanets(texture, size, total) {
        let lambertMaterial2 = new THREE.MeshPhongMaterial({ map: texture });
        let icosahedronGeometry2 = new THREE.IcosahedronGeometry(size, 10);
        
        for ( let i = 0; i < total; i ++ ) {
            var nucleus3 = new THREE.Mesh(icosahedronGeometry2, lambertMaterial2);
            nucleus3.position.setX(8000 * ( 2.0 * Math.random() - 1.0 ));
            nucleus3.position.setY(8000 * ( 2.0 * Math.random() - 1.0 ));
            nucleus3.position.setZ(8000 * ( 2.0 * Math.random() - 1.0 ));
            scene.add(nucleus3);
            palanetasAnimar.push(nucleus3);
        }
    }

    function createStars(texture, size, total,g=0) {
        // var geometry = new THREE.CircleGeometry(size, size);
        var material = new THREE.SpriteMaterial({
            map: texture,
            // transparent: true,
            // alphaTest: 0,
            fog: true
        });

        // var map = THREE.ImageUtils.loadTexture( "sprite.png" );
        var material = new THREE.SpriteMaterial( { map: texture, color: 0xffffff, fog: true } );
        
        for ( let i = 0; i < total; i ++ ) {
            // mesh.position.x = 1000 * ( 2.0 * Math.random() - 1.0 );
            // mesh.position.y = 1000 * ( 2.0 * Math.random() - 1.0 );
            // mesh.position.z = 1000 * ( 2.0 * Math.random() - 1.0 );

            // mesh.matrixAutoUpdate = false;
            // mesh.updateMatrix();

            var sprite = new THREE.Sprite( material );
            sprite.scale.set(size,size,1);
            sprite.position.x = 8000 * ( 2.0 * Math.random() - 1.0 );
            sprite.position.y = 8000 * ( 2.0 * Math.random() - 1.0 );
            sprite.position.z = 8000 * ( 2.0 * Math.random() - 1.0 );
            // sprite.position.set(point.x, point.y, point.z);

            sprite.quaternion.copy(camera.quaternion)
            scene.add( sprite );
            if(g){
                galaxiasAnimar.push(sprite);
            }
            // mesh.lookAt(camera.position);
            // scene.add( mesh );
        }

        // let pointGeometry = new THREE.PlaneGeometry();
        // pointGeometry.vertices = [];
        // let pointMaterial = new THREE.PointsMaterial({
        // 	size: size,
        // 	map: texture,
        // 	blending: THREE.AdditiveBlending,                      
        // });

        // for (let i = 0; i < total; i++) {
        // 	let radius = THREE.MathUtils.randInt(149, 70); 
        // 	let particles = randomPointSphere(radius);
        // 	pointGeometry.vertices.push(particles);
        // }
        // return new THREE.Points(pointGeometry, pointMaterial);
    }
    for(var i=0;i<planetas.length;i++){
        createPlanets(planetas[i],10,100);
    }
    createStars(texture1, 100, 100)
    createStars(texture2, 100, 100)
    createStars(galaxias, 100, 100,1)
    // scene.add(createStars(texture1, 2, 50));   
    // scene.add(createStars(texture2, 3, 20));
    // scene.add(createStars(galaxias, 15, 15));


    // function randomPointSphere (radius) {
    // 	let theta = 2 * Math.PI * Math.random();
    // 	let phi = Math.acos(2 * Math.random() - 1);
        
    // 	// let dx = 0 + (radius * Math.sin(phi) * Math.cos(theta));
    // 	// let dy = 0 + (radius * Math.sin(phi) * Math.sin(theta));
    // 	// let dz = 0 + (radius * Math.cos(phi));
    // 	let dx = 8000 * ( 2.0 * Math.random() - 1.0 );
    // 	let dy  = 8000 * ( 2.0 * Math.random() - 1.0 );
    // 	let dz = 8000 * ( 2.0 * Math.random() - 1.0 );
    // 	return new THREE.Vector3(dx, dy, dz);
    // }

    controls = new FlyControls( camera, renderer.domElement );

    controls.movementSpeed = speedAll;
    controls.domElement = container;
    controls.rollSpeed = 0.3;//Math.PI / 60;
    controls.autoForward = false;
    controls.dragToLook = true;

    //OrbitControl
    // controls = new OrbitControls(camera, renderer.domElement);
    // controls.autoRotate = true;
    // controls.autoRotateSpeed = 0.0008;
    // controls.maxDistance = Infinity;
    // controls.minDistance = -Infinity;
    // controls.enablePan = true;
    // controls.enableZoom = true;
    // controls.activeLook = true;
    // controls.autoForward = true;

    // stats

    // stats = new Stats();
    // container.appendChild( stats.dom );

    // events

    window.addEventListener( 'resize', onWindowResize );
    // window.addEventListener( 'click', onWindowClick );

    // window.addEventListener('wheel', onDocumentMouseMove);


}



//

// function mousemove(event){
// 	// console.log("pageX: ",event.pageX, 
// 	// "pageY: ", event.pageY, 
// 	// "clientX: ", event.clientX, 
// 	// "clientY:", event.clientY);
// 	mousePos = [event.clientX,event.clientY];

// 	currnetMouseX = event.clientX;
// 	currnetMouseY = event.clientY;
// 	mouseDir();
// }

function onWindowResize() {

    renderer.setSize( window.innerWidth, window.innerHeight );

    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
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

    // vx=parseFloat(document.datos[6].value);
    // vy=parseFloat(document.datos[7].value);
    // vz=parseFloat(document.datos[8].value);
    // a=parseFloat(document.datos[9].value);
    // b=parseFloat(document.datos[10].value);
    // c=parseFloat(document.datos[11].value);
    // d=parseFloat(document.datos[12].value);

    var pqx=qx-px;
    var pqy=qy-py;
    var pqz=qz-pz;

    // mx= vy*pqz-vz*pqy ; 
    // my=-vx*pqz+vz*pqx ; 
    // mz= vx*pqy-vy*pqx;
    return m(pqx,pqy,pqz);
    // document.datos.dpq.value= m(pqx,pqy,pqz);//distancia pto a pto
    // document.datos.dpr.value= m(mx,my,mz)/m(vx,vy,vz);//distancia pt a recta
    // document.datos.dpp.value=(a*px+b*py+c*pz+d)/ m(a,b,c); //distancia pto a plano
}

function onPointerMove( event ) {

    // calculate pointer position in normalized device coordinates
    // (-1 to +1) for both components

    // pointer.x = ( event.clientX / window.innerWidth ) * 2 - 1;
    // pointer.y = - ( event.clientY / window.innerHeight ) * 2 + 1;
    // pointer.z = 0.5;

    // raycaster.setFromCamera( pointer, camera );
    // console.log(camera);
    // controls.movementSpeed = 100/controls.getDistance();
    var dG = distancia_x_y(pointer,camera.position);
    if(dG<100){
        dG*=dG;
    }
    controls.movementSpeed = dG;

    var zoom = event.deltaY;
    var moveH = false;
    var moveV = false;
    
    if(event.touches){
        if(event.touches.length>=2){
            var p1 = {"x":event.touches[0].clientX,"y":event.touches[0].clientY,"z":0};
            var p2 = {"x":event.touches[1].clientX,"y":event.touches[1].clientY,"z":0};
            var dP = distancia_x_y(p1,p2);
            if(touchD>dP){
                zoom = 1;
            }else{
                zoom = -1;
            }
            touchD = dP;
        }
        else{
            moveH = event.touches[0].clientX - touchEvent[0].clientX;
            moveV = event.touches[0].clientY - touchEvent[0].clientY;
        }
    }
    // console.log(dG);
    if(moveH){
        if(moveH<0){
            controls.moveState.yawRight = 1;
            controls.moveState.yawLeft = 0;
        }else{
            controls.moveState.yawRight = 0;
            controls.moveState.yawLeft = 1;
        }
    }

    if(moveV){
        if(moveV>0){
            controls.moveState.pitchUp = 1;
            controls.moveState.pitchDown = 0;
        }else{
            controls.moveState.pitchUp = 0;
            controls.moveState.pitchDown = 1;
        }
    }

    if(zoom){
        if(zoom<0){
            controls.moveState.forward = 1;
            controls.moveState.back = 0;
        }else{
            controls.moveState.back = 1;
            controls.moveState.forward = 0;
        }
    }		
    
    controls.updateMovementVector();
    controls.updateRotationVector();

    setTimeout(()=>{
        // controls.moveState.forward = 0;
        // controls.moveState.back = 0;

        // controls.moveState.yawRight = 0;
        // controls.moveState.yawLeft = 0;

        // controls.moveState.pitchUp = 0;
        // controls.moveState.pitchDown = 0;
        controls.moveState = { up: 0, down: 0, left: 0, right: 0, forward: 0, back: 0, pitchUp: 0, pitchDown: 0, yawLeft: 0, yawRight: 0, rollLeft: 0, rollRight: 0 }
        controls.mouseStatus = 0;

        controls.updateMovementVector();
        controls.updateRotationVector();
    },10)
        
    // camera.lookAt(500000,50000,0);
    // camera.translateX(pointer.x);
    // console.log(raycaster.ray.origin.x)
    // controls.panSpeed = 1/controls.getDistance();
    // controls.zoomSpeed = 1/controls.getDistance();
    // controls.dampingFactor = 10/controls.getDistance(); // friction
    // controls.rotateSpeed = 0.1 - controls.dampingFactor; // mouse sensitivity
    // console.log(controls.panSpeed)
}

function goto(e) {

    // update the picking ray with the camera and pointer position
    // raycaster.setFromCamera( pointer, camera );
    // console.log(raycaster);
    pos3d.setX(raycaster.ray.direction.x);
    pos3d.setY(raycaster.ray.direction.y);
    pos3d.setZ(raycaster.ray.direction.z);
    // calculate objects intersecting the picking ray
    // const intersects = raycaster.intersectObjects( scene.children );

    // for ( let i = 0; i < intersects.length; i ++ ) {

    // 	intersects[ i ].object.material.color.set( 0xff0000 );

    // }

    // renderer.render( scene, camera );

}

window.addEventListener( 'wheel', onPointerMove );
window.addEventListener( 'touchstart', function(e){
    touchEvent= e.touches;
});
window.addEventListener( 'touchmove', onPointerMove );

// window.addEventListener( 'pointermove', goto );

// window.requestAnimationFrame(render);

// window.addEventListener( 'pointermove', onPointerMove );

// function mouseDir () {
// 	var bbox = container.getBoundingClientRect();
// 	var mouse3D = new THREE.Vector3 (
// 		((currnetMouseX - bbox.left) / bbox.width) * 2 - 1,
// 		-((currnetMouseY - bbox.top) / bbox.height) * 2 + 1,
// 		0.5
// 	);

// 	// perspective camera
// 	var dir = mouse3D.unproject(camera).sub(camera.position).normalize();
// 	// scene.add(new THREE.ArrowHelper(dir, camera.position));     
// 	camera.lookAt(mouse3D);
// 	// camera.updateMatrixWorld(true);
// 	// mouseDir();
// }

//

function animate() {

    requestAnimationFrame( animate );
    nucleus.rotation.y -= 0.008;

    for(var i=0;i<palanetasAnimar.length;i++){
        palanetasAnimar[i].rotation.y -= 0.008;
    }

    for(var i=0;i<galaxiasAnimar.length;i++){
        galaxiasAnimar[i].material.rotation += 0.000005;
    }
    
    render();
}

function render() {
    // camera.position.x = pos3d.x;
    
    // camera.translateY(pos3d.Y);
    // camera.translateZ(pos3d.Z);
    // camera.updateProjectionMatrix ();
    const delta = clock.getDelta();
    // controls1.update( delta );
    // var screen = 15000;
    // camera.lookAt(5,50000,0);
    // camera.lookAt(raycaster.ray.direction);
    // tmpQuaternion.set( pos3d.x * 100, pos3d.y * 100, pos3d.z* 100, 1 ).normalize();
    // camera.quaternion.multiply( tmpQuaternion )
    // controls.target.set(pointer.x, pointer.y);
    // controls.mouseStatus =0;
    controls.update(delta);
    renderer.render( scene, camera );
    
}

export {controls,camera}