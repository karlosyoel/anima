let renderer,
scene,
camera,
sphereBg,
nucleus,
stars,
controls,
container = document.getElementById("canvas_container"),
timeout_Debounce,
noise = new SimplexNoise(),
cameraSpeed = 0,
blobScale = 0;


init();
animate();


function init() {
    scene = new THREE.Scene();

    camera = new THREE.PerspectiveCamera(55, window.innerWidth / window.innerHeight, 0.01, 1000)
    camera.position.set(0,0,230);

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

    //OrbitControl
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.autoRotate = false;
    controls.autoRotateSpeed = 0.08;
    controls.maxDistance = 950;
    controls.minDistance = 0;
    controls.enablePan = false;
    controls.enableZoom = false;

    const loader = new THREE.TextureLoader();
    const textureSphereBg = loader.load('https://i.ibb.co/4gHcRZD/bg3-je3ddz.jpg');
    const texturenucleus1 = loader.load('https://i.ibb.co/hcN2qXk/star-nc8wkw.jpg');
    const texturenucleus = loader.load('./imgs/earth.jpeg');
    const textureStar = loader.load("https://i.ibb.co/ZKsdYSz/p1-g3zb2a.png");
    const texture1 = loader.load("https://i.ibb.co/F8by6wW/p2-b3gnym.png");  
    const texture2 = loader.load("https://i.ibb.co/yYS2yx5/p3-ttfn70.png");
    const texture4 = loader.load("https://i.ibb.co/yWfKkHh/p4-avirap.png");


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


    /*    Sphere  Background   */
    textureSphereBg.anisotropy = 16;
    let geometrySphereBg = new THREE.SphereBufferGeometry(150, 40, 40);
   
    let materialSphereBg = new THREE.MeshBasicMaterial({
        side: THREE.BackSide,
        map: textureSphereBg,
    });
    sphereBg = new THREE.Mesh(geometrySphereBg, materialSphereBg);
    // scene.add(sphereBg);


    /*    Moving Stars   */
    let starsGeometry = new THREE.Geometry();

    // for (let i = 0; i < 50; i++) {
    //     let particleStar = randomPointSphere(150); 

    //     particleStar.velocity = THREE.MathUtils.randInt(50, 200);

    //     particleStar.startX = particleStar.x;
    //     particleStar.startY = particleStar.y;
    //     particleStar.startZ = particleStar.z;

    //     starsGeometry.vertices.push(particleStar);
    // }

    let starsMaterial = new THREE.PointsMaterial({
        size: 5,
        color: "#ffffff",
        transparent: true,
        opacity: 0.8,
        map: textureStar,
        blending: THREE.AdditiveBlending,
    });
    starsMaterial.depthWrite = false;  
    stars = new THREE.Points(starsGeometry, starsMaterial);
    scene.add(stars);

    /*    Fixed Stars   */
    function createStars(texture, size, total) {
        let pointGeometry = new THREE.Geometry();
        let pointMaterial = new THREE.PointsMaterial({
            size: size,
            map: texture,
            blending: THREE.AdditiveBlending,                      
        });

        for (let i = 0; i < total; i++) {
            let radius = THREE.MathUtils.randInt(149, 70); 
            let particles = randomPointSphere(radius);
            pointGeometry.vertices.push(particles);
        }
        return new THREE.Points(pointGeometry, pointMaterial);
    }
    scene.add(createStars(texture1, 2, 50));   
    scene.add(createStars(texture2, 3, 20));
    scene.add(createStars(texture4, 15, 15));


    function randomPointSphere (radius) {
        let theta = 2 * Math.PI * Math.random();
        let phi = Math.acos(2 * Math.random() - 1);
        let dx = 0 + (radius * Math.sin(phi) * Math.cos(theta));
        let dy = 0 + (radius * Math.sin(phi) * Math.sin(theta));
        let dz = 0 + (radius * Math.cos(phi));
        return new THREE.Vector3(dx, dy, dz);
    }
}


function animate() {

    //Stars  Animation
    stars.geometry.vertices.forEach(function (v) {
        v.x += (0 - v.x) / v.velocity;
        v.y += (0 - v.y) / v.velocity;
        v.z += (0 - v.z) / v.velocity;

        v.velocity -= 0.3;

        if (v.x <= 5 && v.x >= -5 && v.z <= 5 && v.z >= -5) {
            v.x = v.startX;
            v.y = v.startY;
            v.z = v.startZ;
            v.velocity = THREE.MathUtils.randInt(50, 300);
        }
    });


    //Nucleus Animation
    // nucleus.geometry.vertices.forEach(function (v) {
    //     let time = Date.now();
    //     v.normalize();
    //     let distance = nucleus.geometry.parameters.radius + noise.noise3D(
    //         v.x + time * 0.0005,
    //         v.y + time * 0.0003,
    //         v.z + time * 0.0008
    //     ) * blobScale;
    //     v.multiplyScalar(distance);
    // })
    // nucleus.geometry.verticesNeedUpdate = true;
    // nucleus.geometry.normalsNeedUpdate = true;
    // nucleus.geometry.computeVertexNormals();
    // nucleus.geometry.computeFaceNormals();
    nucleus.rotation.y -= 0.002;


    //Sphere Beckground Animation
    sphereBg.rotation.x += 0.000;
    sphereBg.rotation.y += 0.000;
    sphereBg.rotation.z += 0.000;

    
    controls.update();
    stars.geometry.verticesNeedUpdate = true;
    renderer.render(scene, camera);
    requestAnimationFrame(animate);
}


/*     Resize     */
// window.addEventListener("resize", () => {
//     clearTimeout(timeout_Debounce);
//     timeout_Debounce = setTimeout(onWindowResize, 80);
// });
// function onWindowResize() {
//     camera.aspect = container.clientWidth / container.clientHeight;
//     camera.updateProjectionMatrix();
//     renderer.setSize(container.clientWidth, container.clientHeight);
// }

// container.onwheel = (event) =>{
//     console.log("ss");
//     var factor = 15;
//     var mX = (event.clientX / container.clientWidth) * 2 - 1;
//     var mY = -(event.clientY / container.clientHeight) * 2 + 1;
//     var vector = new THREE.Vector3(mX, mY, 0.1);
//     vector.unproject(camera);
//     vector.sub(camera.position);
//     if (event.deltaY < 0) {
//        camera.position.addVectors(camera.position, vector.setLength(factor));
//        controls.target.addVectors(controls.target, vector.setLength(factor));
//     } else {
//        camera.position.subVectors(camera.position, vector.setLength(factor));
//        controls.target.subVectors(controls.target, vector.setLength(factor));
//     }
// };

const width = 960;
const height = 500;
const zoom = d3.zoom()
  .scaleExtent([0, 1000])
  .on('zoom', () => {
    const event = d3.event;
    if (event.sourceEvent) {

      // Get z from D3
      const new_z = event.transform.k;
     
      if (new_z !== camera.position.z) {
        
        // Handle a zoom event
        const { clientX, clientY } = event.sourceEvent;

        // Project a vector from current mouse position and zoom level
        // Find the x and y coordinates for where that vector intersects the new
        // zoom level.
        // Code from WestLangley https://stackoverflow.com/questions/13055214/mouse-canvas-x-y-to-three-js-world-x-y-z/13091694#13091694
        const vector = new THREE.Vector3(
          clientX / width * 2 - 1,
          - (clientY / height) * 2 + 1,
          1 
        );
        vector.unproject(camera);
        const dir = vector.sub(camera.position).normalize();
        const distance = (new_z - camera.position.z)/dir.z;
        const pos = camera.position.clone().add(dir.multiplyScalar(distance));
        
        // Set the camera to new coordinates
        camera.position.set(pos.x, pos.y, new_z);

      } else {

        // Handle panning
        const { movementX, movementY } = event.sourceEvent;

        // Adjust mouse movement by current scale and set camera
        const current_scale = getCurrentScale();
        camera.position.set(camera.position.x - movementX/current_scale, camera.position.y +
          movementY/current_scale, camera.position.z);
      }
    }
  });

  function getCurrentScale() {
    var vFOV = camera.fov * Math.PI / 180
    var scale_height = 2 * Math.tan( vFOV / 2 ) * camera.position.z
    var currentScale = height / scale_height
    return currentScale
  }
// Add zoom listener
const view = d3.select(renderer.domElement);
view.call(zoom);

// Disable double click to zoom because I'm not handling it in Three.js
view.on('dblclick.zoom', null);

// Sync d3 zoom with camera z position
zoom.scaleTo(view, 125);


/*     Fullscreen btn     */
// let fullscreen;
// let fsEnter = document.getElementById('fullscr');
// fsEnter.addEventListener('click', function (e) {
//     e.preventDefault();
//     if (!fullscreen) {
//         fullscreen = true;
//         document.documentElement.requestFullscreen();
//         fsEnter.innerHTML = "Exit Fullscreen";
//     }
//     else {
//         fullscreen = false;
//         document.exitFullscreen();
//         fsEnter.innerHTML = "Go Fullscreen";
//     }
// });