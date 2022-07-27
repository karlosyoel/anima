<html><head>
        <!-- implementation by Charles J. Cliffe, cj@cubicproductions.com -->
        <title>
            CubicVR.js Landscape editor experiment
        </title>
        <!-- <script src="../../CubicVR.js" type="text/javascript"></script>         -->
       <script src="otros/CubicVR.min.js" type="text/javascript"></script>
        <style type="text/css">
            #spatTool {
              z-index:1002;
              position:absolute;
              top:10px;
              color:white;
              font-size: 11px;
              pointer-events:none;
              font-family: Helvetica;
              left:10px;
              width:98%
            }
            
            #spatTool img {
              cursor: pointer;
              margin-left:3px;
              pointer-events:all;            
              border-radius:5px;
            }
            
            #brushStrength {
              pointer-events:all; 
              border-radius: 5px; 
              padding:5px; 
              background: rgba(0,0,0,0.5); 
              width:64px; 
              float:left; 
              height:58px;
            }
            
            #saveTool {
              pointer-events:all; 
              border-radius: 5px; 
              float:right; 
              padding:5px; 
              background: 
              rgba(0,0,0,0.5); 
              margin-left:5px; 
              height:58px;
            }
            
            #helpText {
              border-radius: 5px; 
              background: rgba(0,0,0,0.5); 
              width:295px; 
              height:58px; 
              margin-left:5px; 
              float:right; 
              padding:5px
            }
        </style>
        <script type="application/javascript">
            // 0 = raise, 1 = lower, draw = 2
            var currentTool = 0;
            var spatColor = [0,0,0,1];
            var landscape;
                
            function webGLStart(gl, canvas) {
                var indicator;

                var hfWidth = 256,
                    hfDepth = 256;
                    
                var hfViewWidth = 64,
                    hfViewDepth = 64;
                    
                var brushSize = 30;

                var spatBrush = new CubicVR.DrawBufferBrush({
                  brushType:"sine",
                  op:"replace",
                  size:30,
                  color:[255,0,0,255]
                });
                
                landscape = new CubicVR.Landscape({
                  size: 1000,
                  divX: 256, 
                  divZ: 256, 
                  tileX: 64, 
                  tileZ: 64, 
                  spatResolution: 256,
                  spats: [
                    "imgs/ground_rock.jpg",
                    "imgs/sand.jpg",
                    "imgs/grass2.jpg",
                    "imgs/grass_field.jpg",
                    "imgs/sandstone.jpg"
                  ],
                });
                                                      
                                                      
                var heightField = landscape.getHeightField();
                var heightBrush = new CubicVR.HeightFieldBrush({
                  brushType:"sine",
                  op:"add",
                  size:15,
                  strength:1
                });
                heightField.setBrush(heightBrush);

                var lineBuffer = new CubicVR.Lines({
                  maxPoints:200,
                  color:[0.0,1.0,0.0]
                });

                // New scene with our canvas dimensions and default camera with FOV 80
                var scene = new CubicVR.Scene({
                    camera: {
                        width: canvas.width,
                        height: canvas.height,
                        position: [-1, 60, 100],
                        target: [0, 0, 0],
                        far:2000
                    },
                    light: {
                        type: "directional",
                        direction: [0.2,-0.5,0.4],
                        intensity:0.9
                    }
                });
                scene.setSkyBox(new CubicVR.SkyBox({texture:"imgs/skybox.jpg"}));
                scene.bindSceneObject(landscape);

                // Add our scene to the window resize list
                CubicVR.addResizeable(scene);
                
                var indicatorMesh = new CubicVR.Mesh({
                    primitive: {
                      type: "cone",
                      base: 3.0,
                      height: 5.0,
                      material: {
                          color:[1,1,1],
                          specular:[1.0,1.0,1.0]
                      },
                      uvmapper: {
                            projectionMode: "cubic",
                            scale: [0.5, 0.5, 0.5]
                     },
                     transform: {
                         rotation: [180,0,0],
                         position: [0,2.5,0]
                     }
                    },
                    compile: true
                });
                
                indicator = new CubicVR.SceneObject(indicatorMesh);
                
                scene.bind(indicator);
                
                CubicVR.setGlobalAmbient([0.2,0.2,0.2]);
                
                // initialize a mouse view controller
                var mvc = new CubicVR.MouseViewController(canvas, scene.camera);
                var keyState = mvc.getKeyState();
                var newTarget = null;

                var mvcEvents = {
                    mouseMove: function(ctx, mpos, mdelta, keyState) {
                        var far_pos = scene.camera.unProject(mpos[0], mpos[1], 400);
                        var intersect = CubicVR.vec3.linePlaneIntersect([0, 1, 0], [0, 0, 0], scene.camera.position, far_pos);
                        var spacebr = keyState[CubicVR.keyboard.SPACE]?true:false;
                        
                        var ray_pos = scene.camera.position.slice(0);
                        var ray_dir = CubicVR.vec3.normalize(CubicVR.vec3.subtract(far_pos, scene.camera.position));
                        
                        var edge_intersect = landscape.getHeightField().rayIntersect(ray_pos,ray_dir);
                        
                        if (edge_intersect) {
                        indicator.position[0] = edge_intersect[0];
                        indicator.position[1] = edge_intersect[1];
                        indicator.position[2] = edge_intersect[2];

                      } else {
                        return;
                      }
                        if (ctx.mdown) {
                            if (keyState[CubicVR.keyboard.SHIFT]) {
                                ctx.orbitView(mdelta);
                                if (scene.camera.position[1]<0) {
                                    scene.camera.position[1] = 0;
                                }
                            } else if (currentTool===2) {
                                spatBrush.setColor(spatColor);
                                landscape.drawSpat(edge_intersect[0],edge_intersect[2],spatBrush);
                                
                            } else {
                                
                                if ((currentTool==1 && heightBrush.getStrength()>0) || (currentTool==0 && heightBrush.getStrength()<0)) {
                                  heightBrush.setStrength(-heightBrush.getStrength());
                                } 
                                heightField.draw(
                                  edge_intersect[0],
                                  edge_intersect[2]
                                );
                                    
                            }
                        }
                        // indicator.position[0] = intersect[0];
                        // indicator.position[2] = intersect[2];
                     },
                    mouseWheel: function(ctx, mpos, wheelDelta, keyState) {
//                        ctx.zoomView(wheelDelta * 2);
                        brushSize += wheelDelta/100;
                    },
                };
 
  
                function checkBrushSize(lus) {
                  if (keyState[CubicVR.keyboard.KEY_E]) {
                     brushSize += 40*lus;
                   }
                   if (keyState[CubicVR.keyboard.KEY_Q]) {
                     brushSize -= 40*lus;
                   }
                   
                   if (brushSize != heightBrush.getSize()) {
                     heightBrush.setSize(Math.round(brushSize));
                   }

                   if (brushSize != spatBrush.getSize()) {
                     spatBrush.setSize(Math.round(brushSize));
                   }

                   if (brushSize<1) {
                     brushSize = 1;
                   }
                   if (brushSize> 200) {
                     brushSize = 200;
                   }
                }
                
                function handleMovement(lus) {
                  var moveX, moveY, moveZ;

                  var moveSpeed = keyState[CubicVR.keyboard.SHIFT]?80:40;
                  
                  if (keyState[CubicVR.keyboard.KEY_W]) {
                    moveZ=-1;
                  } else if (keyState[CubicVR.keyboard.KEY_S]) {
                    moveZ=1;
                  } else {
                    moveZ=0;
                  }

                  if (keyState[CubicVR.keyboard.KEY_R]) {
                    moveY=1;
                  } else if (keyState[CubicVR.keyboard.KEY_F]) {
                    moveY=-1;
                  } else {
                    moveY=0;
                  }

                  if (keyState[CubicVR.keyboard.KEY_D]) {
                    moveX=1;
                  } else if (keyState[CubicVR.keyboard.KEY_A]) {
                    moveX=-1;
                  } else {
                    moveX = 0;
                  }
                  
                  if (moveY) {
                    scene.camera.y += moveY*lus*moveSpeed;
                  }
                  
                  if (moveX||moveZ) {
                    var shift_held = keyState[CubicVR.keyboard.SHIFT]?true:false;
                    var alt_held = keyState[CubicVR.keyboard.ALT]?true:false;
                    var vec3 = CubicVR.vec3;
                    
                    var camDir = vec3.normalize(vec3.subtract(scene.camera.position,scene.camera.target));
                    var npos = vec3.add(scene.camera.position,vec3.multiply(camDir,lus*moveZ*moveSpeed));
                    npos = vec3.add(npos,vec3.subtract(vec3.moveViewRelative(scene.camera.position, scene.camera.target, moveX*lus*moveSpeed, 0),scene.camera.position));
                    
                    var camDist = CubicVR.vec2.length([scene.camera.targetX,scene.camera.targetZ],[scene.camera.x,scene.camera.z]);

                    if (camDist > 30) {
                      scene.camera.x += npos[0]-scene.camera.x;
                      scene.camera.z += npos[2]-scene.camera.z;
                    } 

                    var zDelta = npos[1]-scene.camera.y;
                    scene.camera.y += (zDelta>0)?(zDelta/2):(zDelta/4);
                    
                    var targetDir = vec3.normalize(vec3.subtract(indicator.position,scene.camera.target));
                    var targetDist = vec3.length(indicator.position,scene.camera.target);
                    if (targetDist > 80) {
                      targetDist = 80;
                    }
                    var targetSpd = lus*targetDist;
                    scene.camera.target = vec3.add(scene.camera.target,vec3.multiply(targetDir,targetSpd));
                    
                    // mvcEvents.mouseMove(mvc,mvc.getMousePosition(),[0,0],mvc.getKeyState());
                  }
                  
                  var camLandscapeHeight = landscape.getHeightValue(scene.camera.x,scene.camera.z);
                  if (scene.camera.y < camLandscapeHeight+2) {
                    scene.camera.y = camLandscapeHeight+2;
                  }
                }
                
                
                // Start our main drawing loop, it provides a timer and the gl context as parameters
                CubicVR.MainLoop(function(timer, gl) {   
                    var lus = timer.getLastUpdateSeconds();

                    landscape.update();
                    
                    checkBrushSize(lus);
                    handleMovement(lus);
 
                    lineBuffer.clear();
                    for (var i = 0; i < 2.0*Math.PI; i+=Math.PI/100) {
                      var hp_x = indicator.position[0]+Math.sin(i)*brushSize;
                      var hp_z = indicator.position[2]+Math.cos(i)*brushSize;
                      lineBuffer.addPoint([hp_x,landscape.getHeightValue(hp_x,hp_z)+0.1,hp_z]);
                    }
                    lineBuffer.update();
                    
 
                    var orientval = landscape.orient(indicator.x,indicator.z,indicator.getMesh().compiled.bounds[1][0],indicator.getMesh().compiled.bounds[1][2],0);
                    indicator.rotation = orientval[1];

                    scene.render();

                    lineBuffer.render(scene.camera);
                });

                var lBrushStrength = document.getElementById("landscapeBrushStrength");

                for (var i = 1; i <= 10; i++) {
                  lBrushStrength.options.add(new Option(i,i));
                }
                
                function updateUIValues() {
                  lBrushStrength.selectedIndex = parseInt(Math.abs(heightBrush.getStrength()),10)-1;
                }
                
                updateUIValues();
                
                lBrushStrength.addEventListener("change",function() { 
                    heightBrush.setStrength(parseInt(this.options[this.selectedIndex].value,10)); 
                    updateUIValues();  
                },true);

                mvc.setEvents(mvcEvents);
                
                
                // catch dropped audio file
                function dropped(event) {
                    event.preventDefault();

                    var files = event.dataTransfer.files;

                    for (var i = 0; i < files.length; i++) {
                        var file = files[i];
                          landscape.loadFile(file);
                        break;
                    }
                }

                function ignore(event) {
                    event.preventDefault();
                }

                document.addEventListener('dragover', ignore, false);
                document.addEventListener('dragleave', ignore, false);
                document.addEventListener('drop', dropped, false);
            }
            
            
            function selectTool(tool) {
              var tools = ["raiseTool","lowerTool","spatTool0","spatTool1","spatTool2","spatTool3","spatTool4"];
              
              for (var i in tools) {
                if (tool.id == tools[i]) {
                  document.getElementById(tools[i]).style.border="2px solid lightgreen";
                } else {
                  document.getElementById(tools[i]).style.border="2px solid black";                  
                }
              }
            }
            
            function doSave() {
              landscape.saveToJSON(true,true);
            }
        </script>
    </head>
    
    <body onload="CubicVR.start('auto',webGLStart)" wfd-invisible="true">
        <div id="spatTool">
            <img style="border:2px solid lightgreen;" onclick="selectTool(this); currentTool=0;" src="imgs/tool_raise.jpg" id="raiseTool" width="64">
            <img style="border:2px solid black;" onclick="selectTool(this); currentTool=1;" src="imgs/tool_lower.jpg" id="lowerTool" width="64">
            <img style="border:2px solid black;" onclick="selectTool(this); window.spatColor=[0,0,0,0];currentTool=2;" src="imgs/ground_rock.jpg" id="spatTool0" width="64">
            <img style="border:2px solid black;" onclick="selectTool(this); window.spatColor=[255,0,0,0];currentTool=2;" src="imgs/sand.jpg" id="spatTool1" width="64">
            <img style="border:2px solid black;" onclick="selectTool(this); window.spatColor=[0,255,0,0];currentTool=2;" src="imgs/grass2.jpg" id="spatTool2" width="64">
            <img style="border:2px solid black;" onclick="selectTool(this); window.spatColor=[0,0,255,0];currentTool=2;" src="imgs/grass_field.jpg" id="spatTool3" width="64">
            <img style="border:2px solid black;" onclick="selectTool(this); window.spatColor=[0,0,0,255];currentTool=2;" src="imgs/sandstone.jpg" id="spatTool4" width="64">
            
            <div id="saveTool">
              Drop existing file on<br>landscape to load.
              <div style="text-align:center; margin-top:10px"><input type="button" onclick="doSave()" value="Save to file"></div></div>
              
            <div id="helpText">Choose tool or texture and <strong>DRAG</strong> mouse to apply<br>
            <strong>W,A,S,D</strong> to move, <strong>R,F</strong> to rise or fall, <strong>SHIFT</strong> to move quickly.<br>
            <strong>MOUSE WHEEL</strong> or <strong>Q,E</strong> to change brush size<br>
            <strong>SHIFT + DRAG</strong> to orbit view.</div>
            
            
            <div id="brushStrength">
              Height Brush Strength:
              <div style="margin-top:10px; text-align:center;"><select id="landscapeBrushStrength"><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option></select></div>            
            </div>
       </div>
    


<canvas style="left: 0px; top: 0px; position: absolute;" width="726" height="703"></canvas></body></html>