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
			html, body { margin: 0; height: 100%; color: #999; font-family: monospace }
#viewport { display: block; position: absolute; width: 100%; height: 100%; }
#controls { position: absolute; top: 0; left: 0; padding: .25em; z-index: 9999; }
		</style>
	</head>

	<body>
		<div id="control">
			
		</div>
		<canvas id="viewport"></canvas>
<div id="controls">
  <button id="toggle-body">Toggle Body</button>
  <button id="toggle-clothes">Toggle Clothes</button>
  <button id="toggle-skeleton">Toggle Skeleton</button>
  <button id="arms-down">Arms Down</button>
  <button id="arms-up">Arms Up</button>
  <button id="toggle-animation">Toggle Animation</button>
  <p>Multiple THREE.SkinnedMesh (Indexed BufferGeometry) instances sharing a single THREE.Skeleton w/ THREE.AnimationClip</p>
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
			// let camera, scene, renderer;

			let cameraControls;
			var character;
			
			var mixers;
			var light,gltfMain;
			const loader = new GLTFLoader();
			var faceExpres,faceValue;
			var avatar,head, body, pelo;
			var colorPiel = {r:.420,g:.272,b:.179}
			var colorPelo = {r:.0,g:.272,b:.0}
			const SCENE_URL = 'https://gist.githubusercontent.com/satori99/6f642d37999d73fafbdecd5deee0a93b/raw/human.json'

var viewport, renderer, clock, scene, camera
var root, skeleton, body, clothes
var mixer, action,helper,bone

init()

function init() {
	viewport = document.querySelector('#viewport')
	renderer = new THREE.WebGLRenderer( { canvas: viewport, antialias: true } )
	renderer.shadowMap.enabled = true
	renderer.shadowMap.type = THREE.PCFSoftShadowMap
	renderer.setSize( viewport.clientWidth, viewport.clientHeight )
	renderer.clear()
	clock = new THREE.Clock()
	document.body.appendChild( renderer.domElement )
	new THREE.ObjectLoader().load( SCENE_URL, result => {
		scene = result
		// find camera
		camera = scene.getObjectByName( 'Camera' )
		camera.aspect = viewport.clientWidth / viewport.clientHeight
		camera.updateProjectionMatrix()
		// find armature root object.
		// This is a group instance with a userData.bones property
		// containing bones in the same format as would normally be 
		// found on a SkinnedMesh Geometry instance
		root = scene.getObjectByName( 'Human' )
		// console.log(scene);
		// manually create bones and parent them to the root object
		// NOTE: This is normally done in the SkinnedMesh constructor
		const bones = createBones( root, root.userData.bones )
		// Important! must update world matrices before creating skeleton instance
		root.updateMatrixWorld()
		// create skeleton
		// console.log(root)
		skeleton = new THREE.Skeleton( bones, undefined, true )
		// console.log(skeleton);
		// create SkinnedMesh from static mesh geometry
		// console.log(root.getObjectByName( 'Body' ))

		
		body = createSkinnedMesh( root.getObjectByName( 'Body' ), skeleton )
		clothes = createSkinnedMesh( root.getObjectByName( 'Clothes' ), skeleton )
		// create skeleton helper
		helper = new THREE.SkeletonHelper( root )
		scene.add( helper )
		// skeletal animation
		mixer = new THREE.AnimationMixer( root )
		// console.log(scene.animations)
		// action = mixer.clipAction( scene.animations[ 0 ] ).play()
		// button events
		document.querySelector( '#toggle-body' ).onclick = ( e ) => body.visible = ! body.visible 
		document.querySelector( '#toggle-clothes' ).onclick = ( e ) => clothes.visible = ! clothes.visible 
		document.querySelector( '#toggle-skeleton' ).onclick = ( e ) => helper.visible = ! helper.visible
		document.querySelector( '#toggle-animation' ).onclick = ( e ) => action.enabled = ! action.enabled
		document.querySelector( '#arms-down' ).onclick = ( e ) => {
			root.getObjectByName( 'Arm_L' ).rotateY( -Math.PI / 16 )
			root.getObjectByName( 'Arm_R' ).rotateY( Math.PI / 16 )
		}
		document.querySelector( '#arms-up' ).onclick = ( e ) => {
			root.getObjectByName( 'Arm_L' ).rotateY( Math.PI / 16 )
			root.getObjectByName( 'Arm_R' ).rotateY( -Math.PI / 16 )
		}
		// start
		animate()
	} )
}

function animate() {
	requestAnimationFrame( animate )
  	renderer.render( scene, camera )
  // update skeletal animcation
	mixer.update( clock.getDelta() )
  // update skeleton helper
	//helper.update()
  // animate the whole character
  	const v = Date.now() / 2000
	// root.position.x = Math.sin( v )
  	// root.position.z = Math.cos( v )
}

function createBones ( object, jsonBones ) {
	/* adapted from the THREE.SkinnedMesh constructor */
	// create bone instances from json bone data
  const bones = jsonBones.map( gbone => {
		bone = new THREE.Bone()
		bone.name = gbone.name
		bone.position.fromArray( gbone.pos )
		bone.quaternion.fromArray( gbone.rotq )
		if ( gbone.scl !== undefined ) bone.scale.fromArray( gbone.scl )
		return bone
  } )
  // add bone instances to the root object
	jsonBones.forEach( ( gbone, index ) => {
      if ( gbone.parent !== -1 && gbone.parent !== null && bones[ gbone.parent ] !== undefined ) {
        bones[ gbone.parent ].add( bones[ index ] )
      } else {
        object.add( bones[ index ] )
      }
  } )
	return bones
}

function createSkinnedMesh ( mesh, skeleton ) {
	// create SkinnedMesh from static mesh geometry and swap it in the scene graph
  const skinnedMesh = new THREE.SkinnedMesh( mesh.geometry, mesh.material )
  skinnedMesh.castShadow = true
  skinnedMesh.receiveShadow = true
  // bind to skeleton
  skinnedMesh.bind( skeleton, mesh.matrixWorld )
  // swap mesh for skinned mesh
  mesh.parent.add( skinnedMesh )
  mesh.parent.remove( mesh )
  return skinnedMesh
}

		</script>

	</body>
</html>