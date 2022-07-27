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

    const vertShader = `
#define PHONG

varying vec3 vViewPosition;
varying vec3 vNormal;

uniform sampler2D heightMap;
uniform float texelSize;
uniform float texelMaxHeight;

#include <common>

#include <uv_pars_vertex>
#include <uv2_pars_vertex>
#include <displacementmap_pars_vertex>
#include <envmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <shadowmap_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>

vec3 getNormal(vec2 uv) {

    float u = texture2D(heightMap, uv + texelSize * vec2(0.0, -1.0)).r;
    float r = texture2D(heightMap, uv + texelSize * vec2(-1.0, 0.0)).r;
    float l = texture2D(heightMap, uv + texelSize * vec2(1.0, 0.0)).r;
    float d = texture2D(heightMap, uv + texelSize * vec2(0.0, 1.0)).r;

    vec3 n;
    n.z = u - d;
    n.x = r - l;
    n.y = 1.0 / 256.0;
    return normalize(n);
}

void main() {

    #include <uv_vertex>
    #include <uv2_vertex>
    #include <color_vertex>

    #include <beginnormal_vertex>

    #include <begin_vertex>
    
    vec4 height = texture2D(heightMap, vUv);


    vec4 worldPosition = modelMatrix * vec4(transformed, 1.0);
    worldPosition.y += height.r * texelMaxHeight;
    vec4 mvPosition = viewMatrix * worldPosition;

    objectNormal = getNormal(vUv);
    vec3 transformedNormal = objectNormal;
    transformedNormal = normalMatrix * transformedNormal;
    vNormal = normalize( transformedNormal );

    gl_Position = projectionMatrix * mvPosition;

    #include <logdepthbuf_vertex>
    #include <clipping_planes_vertex>

    vViewPosition = - mvPosition.xyz;
//vNormal = vec3(0.0, 0.0, 1.0);

    #include <envmap_vertex>
    #include <shadowmap_vertex>
    #include <fog_vertex>
}`;


    let renderer = new THREE.WebGLRenderer({antialias: true});
    renderer.setSize(window.innerWidth,window.innerHeight);
    document.body.appendChild(renderer.domElement);
    let scene = new THREE.Scene();
    let camera = new THREE.PerspectiveCamera(75, window.innerWidth/window.innerHeight);
    let directionalLight = new THREE.DirectionalLight();
    directionalLight.position.set(-7, 10, -4);
    scene.add(directionalLight);

    const textureLoader = new THREE.TextureLoader();
    textureLoader.load( './maps/heightmap.png', function ( heightmap ) {
        heightmap.wrapS = THREE.RepeatWrapping;
        heightmap.wrapT = THREE.RepeatWrapping;

        heightmap.needsUpdate = true;

        textureLoader.load( './maps/map.png', function ( texture ) {
            let material = new THREE.MeshPhongMaterial({
                map: texture,
                // flatShading: true,
            });

            let uniforms = {
                heightMap: { value: heightmap },
                texelSize: { value: 1.0 / 256 },
                texelMaxHeight: { value: 0.3 },
            };

            material.onBeforeCompile = function (shader) {
                shader.vertexShader = vertShader;
                Object.assign(shader.uniforms, uniforms);
            };

            const planeGeom = new THREE.PlaneBufferGeometry(1, 1, 256, 256);
            planeGeom.rotateX(-Math.PI / 2);
            let mesh = new THREE.Mesh(planeGeom, material);
            scene.add(mesh);
            
        })
    })

  








function animate(s) {
  
  camera.position.set(
    Math.sin(0.001), 
    1, 
    Math.cos(0.001)
  );
  camera.lookAt(new THREE.Vector3(0,0,0))
  renderer.render(scene, camera);
  requestAnimationFrame(animate);
}
animate(0);

</script>