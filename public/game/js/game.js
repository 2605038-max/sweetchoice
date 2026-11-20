/**
 * Sweet Run - Main Game Engine (js/game.js)
 * 
 * Coordinates:
 * - Three.js 3D Scene, Camera, WebGL Renderer, and Warm Bakery Lighting
 * - 3-Lane Giant Biscuit/Cookie Track with Parallax Bakery Environment
 * - Midground Bakery Town with swinging signs and giant cakes
 * - Floating Sprinkles Particle System & Drifting Clouds
 * - Progressive Speed Ramping & Game State Management
 * - Dynamic Obstacles & Cake-Eating Monster Event Handling
 * - Collectibles System with Floating Score Popups & Audio Feedback
 */

class Game {
  constructor() {
    // Game States
    this.STATE_START = 'START';
    this.STATE_PLAYING = 'PLAYING';
    this.STATE_GAME_OVER = 'GAME_OVER';
    this.currentState = this.STATE_START;

    // Gameplay Metrics & Progressive Speed
    this.baseSpeed = 18;       // Starting speed in units/sec
    this.currentSpeed = this.baseSpeed;
    this.maxSpeed = 31;
    this.distance = 0;
    this.score = 0;
    this.treatsCount = 0;

    // DOM Elements
    this.canvas = document.getElementById('game-canvas');
    this.startScreen = document.getElementById('start-screen');
    this.gameOverScreen = document.getElementById('game-over-screen');
    this.startBtn = document.getElementById('start-btn');
    this.restartBtn = document.getElementById('restart-btn');
    this.monsterWarningEl = document.getElementById('monster-warning');
    this.floatingScoresContainer = document.getElementById('floating-scores');
    this.audioToggleBtn = document.getElementById('audio-toggle-btn');

    this.scoreValEl = document.getElementById('score-val');
    this.treatsValEl = document.getElementById('treats-val');
    this.distanceValEl = document.getElementById('distance-val');
    this.speedValEl = document.getElementById('speed-val');
    this.finalScoreValEl = document.getElementById('final-score-val');
    this.finalTreatsValEl = document.getElementById('final-treats-val');
    this.finalDistanceValEl = document.getElementById('final-distance-val');

    // Touch controls tracking
    this.touchStartX = 0;
    this.touchStartY = 0;

    // Environmental animation timer
    this.envTimer = 0;

    // Initialize systems
    this.initThree();
    this.initLights();
    this.initTrack();
    this.initBakeryTown();
    this.initCloudsAndAtmosphere();
    this.initEntities();
    this.initEvents();

    // Start render loop
    this.clock = new THREE.Clock();
    this.animate = this.animate.bind(this);
    requestAnimationFrame(this.animate);
  }

  /**
   * Set up Three.js Scene, Camera, and WebGL Renderer
   */
  initThree() {
    // 1. Scene with sweet candy-colored fog
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0xffe6f0); // Soft candy pink sky
    this.scene.fog = new THREE.FogExp2(0xffe6f0, 0.010);

    // 2. Perspective Camera (viewing from behind & slightly above player)
    const aspect = window.innerWidth / window.innerHeight;
    this.camera = new THREE.PerspectiveCamera(58, aspect, 0.1, 350);
    this.cameraDefaultPos = new THREE.Vector3(0, 3.8, 6.2);
    this.cameraLookAt = new THREE.Vector3(0, 1.2, -6);
    this.camera.position.copy(this.cameraDefaultPos);
    this.camera.lookAt(this.cameraLookAt);

    // 3. WebGL Renderer
    this.renderer = new THREE.WebGLRenderer({
      canvas: this.canvas,
      antialias: true,
      powerPreference: 'high-performance'
    });
    this.renderer.setSize(window.innerWidth, window.innerHeight);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  }

  /**
   * Set up warm, colorful lighting for the magical bakery world
   */
  initLights() {
    // Soft ambient light
    const ambientLight = new THREE.AmbientLight(0xfff5f8, 0.8);
    this.scene.add(ambientLight);

    // Warm directional sunlight
    this.dirLight = new THREE.DirectionalLight(0xfffaed, 0.85);
    this.dirLight.position.set(20, 30, 15);
    this.dirLight.castShadow = true;
    this.dirLight.shadow.mapSize.width = 1024;
    this.dirLight.shadow.mapSize.height = 1024;
    this.dirLight.shadow.camera.near = 0.5;
    this.dirLight.shadow.camera.far = 120;
    this.dirLight.shadow.camera.left = -20;
    this.dirLight.shadow.camera.right = 20;
    this.dirLight.shadow.camera.top = 30;
    this.dirLight.shadow.camera.bottom = -20;
    this.scene.add(this.dirLight);

    // Subtle hemisphere light for candy glow
    const hemiLight = new THREE.HemisphereLight(0xfff0f5, 0xffd1dc, 0.4);
    this.scene.add(hemiLight);
  }

  /**
   * Build the 3-lane cookie/biscuit track with roadside bakery decorations
   */
  initTrack() {
    this.trackSegments = [];
    this.segmentLength = 40;
    this.totalSegments = 5;
    this.roadWidth = 8.0;

    for (let i = 0; i < this.totalSegments; i++) {
      const segment = this.createTrackSegment();
      segment.position.z = -i * this.segmentLength + 10;
      this.scene.add(segment);
      this.trackSegments.push(segment);
    }
  }

  /**
   * Creates a single track segment with cookie road, frosting stripes, and roadside sweets
   */
  createTrackSegment() {
    const segment = new THREE.Group();

    // 1. Giant Cookie / Biscuit Road
    const roadGeo = new THREE.PlaneGeometry(this.roadWidth, this.segmentLength);
    const roadMat = new THREE.MeshLambertMaterial({
      color: 0xf5caa6, // Sweet golden-baked waffle wafer color
      side: THREE.DoubleSide
    });
    const road = new THREE.Mesh(roadGeo, roadMat);
    road.rotation.x = -Math.PI / 2;
    road.receiveShadow = true;
    segment.add(road);

    // Waffle grid embossing details along road
    const gridLineMat = new THREE.MeshBasicMaterial({ color: 0xdeb887 });
    for (let g = 0; g < 10; g++) {
      const gLine = new THREE.Mesh(new THREE.PlaneGeometry(this.roadWidth, 0.08), gridLineMat);
      gLine.rotation.x = -Math.PI / 2;
      gLine.position.set(0, 0.005, -this.segmentLength / 2 + g * 4);
      segment.add(gLine);
    }

    // 2. White Icing Lane Dividers (separating Left, Center, Right lanes)
    const stripeMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
    const numDashes = 10;
    const dashLength = 2.0;

    for (let d = 0; d < numDashes; d++) {
      const zOffset = -this.segmentLength / 2 + (d * 4) + 1.5;
      
      const leftDash = new THREE.Mesh(new THREE.PlaneGeometry(0.14, dashLength), stripeMat);
      leftDash.rotation.x = -Math.PI / 2;
      leftDash.position.set(-1.2, 0.01, zOffset);
      segment.add(leftDash);

      const rightDash = new THREE.Mesh(new THREE.PlaneGeometry(0.14, dashLength), stripeMat);
      rightDash.rotation.x = -Math.PI / 2;
      rightDash.position.set(1.2, 0.01, zOffset);
      segment.add(rightDash);
    }

    // 3. Strawberry Wafer Curbs on edges
    const curbGeo = new THREE.BoxGeometry(0.5, 0.35, this.segmentLength);
    const curbMat = new THREE.MeshLambertMaterial({ color: 0xff6699 });

    const leftCurb = new THREE.Mesh(curbGeo, curbMat);
    leftCurb.position.set(-this.roadWidth / 2 - 0.25, 0.15, 0);
    leftCurb.receiveShadow = true;
    segment.add(leftCurb);

    const rightCurb = new THREE.Mesh(curbGeo, curbMat);
    rightCurb.position.set(this.roadWidth / 2 + 0.25, 0.15, 0);
    rightCurb.receiveShadow = true;
    segment.add(rightCurb);

    // 4. Roadside Sweets (Foreground Parallax Layer)
    // Left & Right roadside decorations: Lollipop trees, candy canes, giant cupcakes
    const sideX = this.roadWidth / 2 + 2.2;

    for (let s = 0; s < 3; s++) {
      const zPos = -this.segmentLength / 2 + s * 13 + 6;

      // Left Lollipop Tree
      const loliGroup = this.createLollipopTree();
      loliGroup.position.set(-sideX - (s % 2 === 0 ? 0.8 : 0.2), 0, zPos);
      segment.add(loliGroup);

      // Right Giant Roadside Cupcake
      const cupcakeDeco = this.createRoadsideCupcake();
      cupcakeDeco.position.set(sideX + (s % 2 === 0 ? 0.3 : 0.9), 0, zPos);
      segment.add(cupcakeDeco);
    }

    return segment;
  }

  /**
   * Roadside Lollipop Tree
   */
  createLollipopTree() {
    const group = new THREE.Group();

    // Candy cane striped stick
    const stickGeo = new THREE.CylinderGeometry(0.12, 0.12, 2.8, 10);
    const stickMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const stick = new THREE.Mesh(stickGeo, stickMat);
    stick.position.y = 1.4;
    stick.castShadow = true;
    group.add(stick);

    // Swirled lollipop candy head
    const colors = [0xff4d88, 0x06d6a0, 0xffd166, 0x118ab2];
    const headGeo = new THREE.CylinderGeometry(0.9, 0.9, 0.25, 18);
    const headMat = new THREE.MeshLambertMaterial({
      color: colors[Math.floor(Math.random() * colors.length)]
    });
    const head = new THREE.Mesh(headGeo, headMat);
    head.rotation.x = Math.PI / 2;
    head.position.y = 2.8;
    head.castShadow = true;
    group.add(head);

    return group;
  }

  /**
   * Roadside Giant Cupcake decoration
   */
  createRoadsideCupcake() {
    const group = new THREE.Group();

    const baseGeo = new THREE.CylinderGeometry(0.8, 0.6, 0.9, 14);
    const baseMat = new THREE.MeshLambertMaterial({ color: 0xdeb887 });
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 0.45;
    base.castShadow = true;
    group.add(base);

    const frostingGeo = new THREE.SphereGeometry(0.75, 12, 12);
    const frostingColors = [0xff6b8b, 0xffffff, 0x00c49f, 0xffbe0b];
    const frostingMat = new THREE.MeshLambertMaterial({
      color: frostingColors[Math.floor(Math.random() * frostingColors.length)]
    });
    const frosting = new THREE.Mesh(frostingGeo, frostingMat);
    frosting.position.y = 1.1;
    frosting.castShadow = true;
    group.add(frosting);

    const cherry = new THREE.Mesh(
      new THREE.SphereGeometry(0.2, 8, 8),
      new THREE.MeshLambertMaterial({ color: 0xd90429 })
    );
    cherry.position.y = 1.8;
    group.add(cherry);

    return group;
  }

  /**
   * Build the Midground Bakery Town (Parallax Layer)
   * Gingerbread shops, pastry houses with striped awnings & swinging bakery signs
   */
  initBakeryTown() {
    this.bakerySegments = [];
    this.bakerySegmentLength = 60;
    this.totalBakerySegments = 4;
    this.bakerySpeedMultiplier = 0.62; // Moderate scroll speed for 3D parallax depth!

    this.bakeryGroup = new THREE.Group();
    this.scene.add(this.bakeryGroup);

    this.swingingSigns = [];

    for (let i = 0; i < this.totalBakerySegments; i++) {
      const bSegment = this.createBakerySegment();
      bSegment.position.z = -i * this.bakerySegmentLength + 10;
      this.bakeryGroup.add(bSegment);
      this.bakerySegments.push(bSegment);
    }
  }

  createBakerySegment() {
    const segment = new THREE.Group();
    const midX = 17; // Placed nicely in middle distance

    // Left Bakery Shop
    const leftShop = this.createBakeryShop(0xff8da1, 0xffffff);
    leftShop.position.set(-midX, 0, -15);
    leftShop.rotation.y = 0.2;
    segment.add(leftShop);

    // Right Giant Celebration Cake
    const giantCake = this.createGiantCake();
    giantCake.position.set(midX + 2, 0, -20);
    giantCake.rotation.y = -0.25;
    segment.add(giantCake);

    // Another shop further along segment
    const rightShop = this.createBakeryShop(0x06d6a0, 0xffd166);
    rightShop.position.set(midX + 1, 0, 15);
    rightShop.rotation.y = -0.15;
    segment.add(rightShop);

    return segment;
  }

  /**
   * Bakery House with striped awning and swinging sign
   */
  createBakeryShop(wallColor = 0xffa8ba, awningColor = 0xffffff) {
    const house = new THREE.Group();

    // Main house building
    const wallGeo = new THREE.BoxGeometry(6.5, 4.5, 6);
    const wallMat = new THREE.MeshLambertMaterial({ color: wallColor });
    const walls = new THREE.Mesh(wallGeo, wallMat);
    walls.position.y = 2.25;
    house.add(walls);

    // Roof (Pastel wafer roof)
    const roofGeo = new THREE.ConeGeometry(5.2, 2.4, 4);
    const roofMat = new THREE.MeshLambertMaterial({ color: 0x8b5a2b });
    const roof = new THREE.Mesh(roofGeo, roofMat);
    roof.position.y = 5.6;
    roof.rotation.y = Math.PI / 4;
    house.add(roof);

    // Striped Awning over window
    const awningGeo = new THREE.BoxGeometry(4.2, 0.4, 1.8);
    const awningMat = new THREE.MeshLambertMaterial({ color: awningColor });
    const awning = new THREE.Mesh(awningGeo, awningMat);
    awning.position.set(0, 3.2, 3.5);
    awning.rotation.x = 0.25;
    house.add(awning);

    // Swinging Bakery Sign
    const signPivot = new THREE.Group();
    signPivot.position.set(0, 4.4, 3.2);

    const signPole = new THREE.Mesh(
      new THREE.CylinderGeometry(0.06, 0.06, 1.0),
      new THREE.MeshBasicMaterial({ color: 0x333333 })
    );
    signPole.position.y = -0.5;
    signPivot.add(signPole);

    const signBoard = new THREE.Mesh(
      new THREE.BoxGeometry(1.6, 0.7, 0.1),
      new THREE.MeshLambertMaterial({ color: 0xffd166 })
    );
    signBoard.position.y = -0.8;
    signPivot.add(signBoard);

    house.add(signPivot);
    this.swingingSigns.push(signPivot);

    return house;
  }

  /**
   * Giant 3-Tier Bakery Cake in midground
   */
  createGiantCake() {
    const cake = new THREE.Group();

    const t1 = new THREE.Mesh(
      new THREE.CylinderGeometry(3.5, 3.5, 1.6, 16),
      new THREE.MeshLambertMaterial({ color: 0xffccd5 })
    );
    t1.position.y = 0.8;
    cake.add(t1);

    const t2 = new THREE.Mesh(
      new THREE.CylinderGeometry(2.4, 2.4, 1.4, 16),
      new THREE.MeshLambertMaterial({ color: 0xffffff })
    );
    t2.position.y = 2.3;
    cake.add(t2);

    const t3 = new THREE.Mesh(
      new THREE.CylinderGeometry(1.5, 1.5, 1.2, 16),
      new THREE.MeshLambertMaterial({ color: 0xff70a6 })
    );
    t3.position.y = 3.6;
    cake.add(t3);

    // Glowing candle on top
    const candle = new THREE.Mesh(
      new THREE.CylinderGeometry(0.2, 0.2, 1.2, 8),
      new THREE.MeshLambertMaterial({ color: 0xffd166 })
    );
    candle.position.y = 4.8;
    cake.add(candle);

    const flame = new THREE.Mesh(
      new THREE.SphereGeometry(0.3, 8, 8),
      new THREE.MeshBasicMaterial({ color: 0xff5400 })
    );
    flame.position.y = 5.6;
    cake.add(flame);

    return cake;
  }

  /**
   * Drifting clouds and floating sprinkles atmosphere
   */
  initCloudsAndAtmosphere() {
    // 1. Soft Drifting Clouds (Slow Background Layer)
    this.clouds = [];
    const cloudGeo = new THREE.SphereGeometry(3.5, 10, 10);
    const cloudMat = new THREE.MeshBasicMaterial({
      color: 0xffffff,
      transparent: true,
      opacity: 0.65
    });

    for (let c = 0; c < 8; c++) {
      const cloud = new THREE.Group();
      for (let p = 0; p < 4; p++) {
        const puff = new THREE.Mesh(cloudGeo, cloudMat);
        puff.position.set(p * 2.8 - 4, Math.sin(p) * 1.2, (p % 2) * 1.5);
        puff.scale.set(1, 0.65, 0.9);
        cloud.add(puff);
      }
      cloud.position.set(
        (Math.random() - 0.5) * 120,
        22 + Math.random() * 12,
        -Math.random() * 200 - 30
      );
      this.scene.add(cloud);
      this.clouds.push(cloud);
    }

    // 2. Floating Sprinkles Particle Cloud
    this.sprinkles = [];
    const sprinkleGeo = new THREE.BoxGeometry(0.08, 0.08, 0.22);
    const colors = [0xff4d88, 0xffd166, 0x06d6a0, 0x118ab2, 0xffffff];

    for (let i = 0; i < 75; i++) {
      const color = colors[i % colors.length];
      const mat = new THREE.MeshBasicMaterial({ color });
      const sp = new THREE.Mesh(sprinkleGeo, mat);
      sp.position.set(
        (Math.random() - 0.5) * 20,
        0.5 + Math.random() * 5.5,
        -Math.random() * 60 + 5
      );
      sp.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, 0);
      sp.rotSpeed = {
        x: (Math.random() - 0.5) * 3,
        y: (Math.random() - 0.5) * 3
      };
      this.scene.add(sp);
      this.sprinkles.push(sp);
    }
  }

  /**
   * Instantiate Player, Dynamic ObstacleManager, and CollectibleManager
   */
  initEntities() {
    // 1. Player (Cute 3D Candy Runner Girl)
    this.player = new Player();
    this.scene.add(this.player.mesh);

    // 2. Dynamic Obstacle Manager with Monster Warning Callback
    this.obstacleManager = new ObstacleManager(this.scene, (show) => {
      this.toggleMonsterWarning(show);
    });

    // 3. Collectibles Manager with Pickup Callback
    this.collectibleManager = new CollectibleManager(this.scene, (item) => {
      this.handleCollectiblePickup(item);
    });
  }

  /**
   * Show/hide the Monster Warning banner
   */
  toggleMonsterWarning(show) {
    if (show) {
      this.monsterWarningEl.classList.remove('hidden');
    } else {
      this.monsterWarningEl.classList.add('hidden');
    }
  }

  /**
   * Handle picking up treats / coins / cakes
   */
  handleCollectiblePickup(item) {
    this.score += item.points;
    this.treatsCount += 1;

    // Update HUD values
    this.scoreValEl.textContent = Math.floor(this.score);
    this.treatsValEl.textContent = this.treatsCount;

    // Show floating score indicator
    this.spawnFloatingScore(`+${item.points}`, item.isGold);
  }

  /**
   * Create an animated floating text popup on screen
   */
  spawnFloatingScore(text, isGold = false) {
    const el = document.createElement('div');
    el.className = `floating-score${isGold ? ' gold' : ''}`;
    el.textContent = text;
    // Position near screen center
    el.style.left = '50%';
    el.style.top = '45%';
    this.floatingScoresContainer.appendChild(el);

    setTimeout(() => {
      if (el.parentNode) {
        el.parentNode.removeChild(el);
      }
    }, 850);
  }

  /**
   * Event Listeners
   */
  initEvents() {
    // Audio button toggle
    if (this.audioToggleBtn) {
      this.audioToggleBtn.addEventListener('click', () => {
        if (window.soundEngine) {
          window.soundEngine.init();
          const muted = window.soundEngine.toggleMute();
          this.audioToggleBtn.textContent = muted ? '🔇' : '🔊';
        }
      });
    }

    // Button clicks
    this.startBtn.addEventListener('click', () => {
      if (window.soundEngine) window.soundEngine.init();
      this.startGame();
    });

    this.restartBtn.addEventListener('click', () => {
      if (window.soundEngine) window.soundEngine.init();
      this.restartGame();
    });

    // Keyboard controls
    window.addEventListener('keydown', (e) => {
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' '].includes(e.key)) {
        e.preventDefault();
      }

      if (this.currentState === this.STATE_START && (e.key === ' ' || e.key === 'Enter')) {
        if (window.soundEngine) window.soundEngine.init();
        this.startGame();
        return;
      }
      if (this.currentState === this.STATE_GAME_OVER && (e.key === ' ' || e.key === 'Enter')) {
        if (window.soundEngine) window.soundEngine.init();
        this.restartGame();
        return;
      }

      if (this.currentState === this.STATE_PLAYING) {
        switch (e.key) {
          case 'ArrowLeft':
          case 'a':
          case 'A':
            this.player.moveLeft();
            break;
          case 'ArrowRight':
          case 'd':
          case 'D':
            this.player.moveRight();
            break;
          case 'ArrowUp':
          case 'w':
          case 'W':
          case ' ':
            this.player.jump();
            if (window.soundEngine) {
              window.soundEngine.playJump();
            }
            break;
        }
      }
    });

    // Touch / Mobile Swipe Controls
    window.addEventListener('touchstart', (e) => {
      if (window.soundEngine) window.soundEngine.init();
      if (e.touches.length > 0) {
        this.touchStartX = e.touches[0].clientX;
        this.touchStartY = e.touches[0].clientY;
      }
    }, { passive: true });

    window.addEventListener('touchend', (e) => {
      if (e.changedTouches.length > 0 && this.currentState === this.STATE_PLAYING) {
        const deltaX = e.changedTouches[0].clientX - this.touchStartX;
        const deltaY = e.changedTouches[0].clientY - this.touchStartY;
        const minSwipe = 28;

        if (Math.abs(deltaX) > Math.abs(deltaY)) {
          if (deltaX > minSwipe) {
            this.player.moveRight();
          } else if (deltaX < -minSwipe) {
            this.player.moveLeft();
          }
        } else {
          if (deltaY < -minSwipe) {
            this.player.jump();
            if (window.soundEngine) {
              window.soundEngine.playJump();
            }
          }
        }
      }
    }, { passive: true });

    // Resize handler
    window.addEventListener('resize', () => {
      this.camera.aspect = window.innerWidth / window.innerHeight;
      this.camera.updateProjectionMatrix();
      this.renderer.setSize(window.innerWidth, window.innerHeight);
    });
  }

  /**
   * Start a new game session
   */
  startGame() {
    this.startScreen.classList.add('hidden');
    this.gameOverScreen.classList.add('hidden');
    this.currentState = this.STATE_PLAYING;
    this.distance = 0;
    this.score = 0;
    this.treatsCount = 0;
    this.currentSpeed = this.baseSpeed;
    
    this.treatsValEl.textContent = '0';
    this.scoreValEl.textContent = '0';
    this.distanceValEl.textContent = '0m';

    this.player.reset();
    this.obstacleManager.reset();
    this.collectibleManager.reset();
    this.toggleMonsterWarning(false);
  }

  /**
   * Restart game
   */
  restartGame() {
    this.startGame();
  }

  /**
   * Trigger Game Over
   */
  gameOver() {
    this.currentState = this.STATE_GAME_OVER;
    this.finalScoreValEl.textContent = Math.floor(this.score);
    this.finalTreatsValEl.textContent = this.treatsCount;
    this.finalDistanceValEl.textContent = `${Math.floor(this.distance)}m`;
    this.gameOverScreen.classList.remove('hidden');
    this.toggleMonsterWarning(false);

    if (window.soundEngine) {
      window.soundEngine.playHit();
    }
  }

  /**
   * Multi-layer Parallax Track & Bakery Environment Movement
   */
  updateEnvironment(speed, delta) {
    this.envTimer += delta;

    // 1. Foreground Track Movement & Recycling
    const trackMove = speed * delta;
    for (let segment of this.trackSegments) {
      segment.position.z += trackMove;
      if (segment.position.z > 25) {
        let minZ = 0;
        for (let s of this.trackSegments) {
          if (s.position.z < minZ) minZ = s.position.z;
        }
        segment.position.z = minZ - this.segmentLength;
      }
    }

    // 2. Midground Bakery Town Movement & Recycling (Parallax depth!)
    const bakeryMove = speed * this.bakerySpeedMultiplier * delta;
    for (let bSeg of this.bakerySegments) {
      bSeg.position.z += bakeryMove;
      if (bSeg.position.z > 25) {
        let minBZ = 0;
        for (let b of this.bakerySegments) {
          if (b.position.z < minBZ) minBZ = b.position.z;
        }
        bSeg.position.z = minBZ - this.bakerySegmentLength;
      }
    }

    // 3. Gentle Swinging Bakery Signs
    for (let sign of this.swingingSigns) {
      sign.rotation.z = Math.sin(this.envTimer * 2.5) * 0.15;
    }

    // 4. Slow Cloud Drift (Distant Sky Layer)
    for (let cloud of this.clouds) {
      cloud.position.x += delta * 1.2;
      cloud.position.z += speed * 0.08 * delta;
      if (cloud.position.x > 80) {
        cloud.position.x = -80;
      }
      if (cloud.position.z > 20) {
        cloud.position.z = -220;
      }
    }

    // 5. Floating Sprinkles Tumbling in the Breeze
    for (let sp of this.sprinkles) {
      sp.position.z += speed * 0.9 * delta;
      sp.position.y += Math.sin(this.envTimer * 3 + sp.position.x) * 0.01;
      sp.rotation.x += sp.rotSpeed.x * delta;
      sp.rotation.y += sp.rotSpeed.y * delta;

      if (sp.position.z > 10) {
        sp.position.z = -55 - Math.random() * 20;
        sp.position.x = (Math.random() - 0.5) * 18;
      }
    }
  }

  /**
   * Main game loop
   */
  animate() {
    requestAnimationFrame(this.animate);

    const delta = Math.min(this.clock.getDelta(), 0.1);

    if (this.currentState === this.STATE_PLAYING) {
      // 1. Progressive Speed Ramping
      // Speed increases smoothly from 18 to ~31 as distance increases
      this.currentSpeed = Math.min(this.maxSpeed, this.baseSpeed + (this.distance / 120) * 1.25);

      // 2. Distance & Survival Score
      this.distance += this.currentSpeed * delta;
      this.score += delta * 12;

      // Update HUD
      this.scoreValEl.textContent = Math.floor(this.score);
      this.distanceValEl.textContent = `${Math.floor(this.distance)}m`;
      this.speedValEl.textContent = `${(this.currentSpeed / this.baseSpeed).toFixed(1)}x`;

      // 3. Parallax Environment Scrolling
      this.updateEnvironment(this.currentSpeed, delta);

      // 4. Player Physics & Animation
      this.player.update(delta, true);

      // 5. Dynamic Obstacles & Monster Events
      this.obstacleManager.update(this.currentSpeed, delta, this.distance, this.player);
      
      if (this.obstacleManager.checkCollision(this.player)) {
        this.gameOver();
      }

      // 6. Collectibles & Collision
      this.collectibleManager.update(this.currentSpeed, delta, this.distance);
      this.collectibleManager.checkCollisions(this.player);

      // Camera smoothly follows player lane with gentle damping
      this.camera.position.x = THREE.MathUtils.lerp(
        this.camera.position.x,
        this.player.mesh.position.x * 0.4,
        delta * 6
      );
    } else {
      // Idle pose & gentle scenic drift when in Start / Game Over
      this.player.update(delta, false);
      this.updateEnvironment(3.5, delta);
    }

    // Render 3D Scene
    this.renderer.render(this.scene, this.camera);
  }
}

// Instantiate game once window loads
window.addEventListener('DOMContentLoaded', () => {
  window.sweetRunGame = new Game();
});
