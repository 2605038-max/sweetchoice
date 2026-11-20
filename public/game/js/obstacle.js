/**
 * Sweet Run - Dynamic Obstacles & Cake-Eating Monster (js/obstacle.js)
 * 
 * Features:
 * 1. Dynamic Obstacle Types:
 *    - Stationary Chocolate Hurdle (jumpable / dodgeable)
 *    - Sliding Jelly Block (glides smoothly side-to-side across lanes)
 *    - Bouncing Cupcake (bounces up and down)
 *    - Rolling Donut (large frosted donut rolling toward player)
 *    - Swinging Candy Cane (swings like a pendulum)
 *    - Pop-up Cake (rises up from the biscuit road as player nears)
 *    - Rotating Wafer Cookie (spins continuously)
 *    - Dual-obstacle combinations (always leaving 1 safe lane)
 * 2. Cake-Eating Monster:
 *    - Funny, cute, hungry monster with frosting and crumbs around mouth
 *    - Warning banner & sound cue 2.5s before rushing toward player
 *    - Runs down the road faster than normal obstacles
 * 3. Progressive difficulty system (fair & balanced)
 */

const OBSTACLE_TYPES = {
  HURDLE: 'hurdle',
  SLIDING: 'sliding',
  BOUNCING: 'bouncing',
  ROLLING: 'rolling',
  SWINGING: 'swinging',
  POPUP: 'popup',
  ROTATING: 'rotating'
};

class Obstacle {
  /**
   * @param {string} type - Obstacle type from OBSTACLE_TYPES
   * @param {number} lane - Lane index (-1: Left, 0: Center, 1: Right)
   * @param {number} zPos - Initial Z position ahead
   * @param {number} laneWidth - Width between lanes
   * @param {object} options - Dynamic motion params
   */
  constructor(type = OBSTACLE_TYPES.HURDLE, lane = 0, zPos = -80, laneWidth = 2.4, options = {}) {
    this.type = type;
    this.lane = lane;
    this.baseX = lane * laneWidth;
    this.zPos = zPos;
    this.laneWidth = laneWidth;
    this.options = options;
    this.time = Math.random() * 10;
    this.active = true;

    this.mesh = new THREE.Group();
    this.boundingBox = new THREE.Box3();

    // Crumb dust particles when pop-up or landing
    this.hasPoppedUp = (this.type !== OBSTACLE_TYPES.POPUP);
    this.popupProgress = this.hasPoppedUp ? 1 : 0;

    this.createModel();
    this.mesh.position.set(this.baseX, 0, this.zPos);
    this.updateBoundingBox();
  }

  createModel() {
    switch (this.type) {
      case OBSTACLE_TYPES.SLIDING:
        this.createSlidingJelly();
        break;
      case OBSTACLE_TYPES.BOUNCING:
        this.createBouncingCupcake();
        break;
      case OBSTACLE_TYPES.ROLLING:
        this.createRollingDonut();
        break;
      case OBSTACLE_TYPES.SWINGING:
        this.createSwingingCandyCane();
        break;
      case OBSTACLE_TYPES.POPUP:
        this.createPopupCake();
        break;
      case OBSTACLE_TYPES.ROTATING:
        this.createRotatingCookie();
        break;
      case OBSTACLE_TYPES.HURDLE:
      default:
        this.createChocolateHurdle();
        break;
    }
  }

  /**
   * 1. Classic Chocolate Hurdle: Jumpable / dodgeable block with cherry
   */
  createChocolateHurdle() {
    const blockWidth = 1.7;
    const blockHeight = 0.85;
    const blockDepth = 0.8;

    const chocolateGeo = new THREE.BoxGeometry(blockWidth, blockHeight, blockDepth);
    const chocolateMat = new THREE.MeshLambertMaterial({ color: 0x4a2c1d });
    const block = new THREE.Mesh(chocolateGeo, chocolateMat);
    block.position.y = blockHeight / 2;
    block.castShadow = true;
    block.receiveShadow = true;
    this.mesh.add(block);

    // Chunks & drizzle
    const segmentGeo = new THREE.BoxGeometry(0.65, 0.15, 0.65);
    const segmentMat = new THREE.MeshLambertMaterial({ color: 0x5a3422 });
    const leftChunk = new THREE.Mesh(segmentGeo, segmentMat);
    leftChunk.position.set(-0.45, blockHeight + 0.05, 0);
    this.mesh.add(leftChunk);

    const rightChunk = new THREE.Mesh(segmentGeo, segmentMat);
    rightChunk.position.set(0.45, blockHeight + 0.05, 0);
    this.mesh.add(rightChunk);

    // Vanilla drizzle
    const icingGeo = new THREE.CylinderGeometry(0.04, 0.04, 1.5, 8);
    const icingMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
    const icing1 = new THREE.Mesh(icingGeo, icingMat);
    icing1.rotation.z = Math.PI / 2;
    icing1.position.set(0, blockHeight + 0.12, 0);
    this.mesh.add(icing1);

    // Cherry on top
    const cherryGeo = new THREE.SphereGeometry(0.18, 12, 12);
    const cherryMat = new THREE.MeshLambertMaterial({ color: 0xd90429 });
    const cherry = new THREE.Mesh(cherryGeo, cherryMat);
    cherry.position.set(0, blockHeight + 0.22, 0);
    this.mesh.add(cherry);
  }

  /**
   * 2. Sliding Jelly Block: Slides side-to-side between adjacent lanes
   */
  createSlidingJelly() {
    const geo = new THREE.CylinderGeometry(0.7, 0.8, 1.0, 16);
    const mat = new THREE.MeshLambertMaterial({
      color: 0x00c49f, // Lime mint jelly
      transparent: true,
      opacity: 0.88
    });
    this.jellyMesh = new THREE.Mesh(geo, mat);
    this.jellyMesh.position.y = 0.5;
    this.jellyMesh.castShadow = true;
    this.mesh.add(this.jellyMesh);

    // Sugar crystal sparkles on top
    const crystalGeo = new THREE.DodecahedronGeometry(0.15);
    const crystalMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const crystal = new THREE.Mesh(crystalGeo, crystalMat);
    crystal.position.set(0, 1.05, 0);
    this.mesh.add(crystal);
  }

  /**
   * 3. Bouncing Cupcake: Jumps up and down rhythmically
   */
  createBouncingCupcake() {
    this.cupcakeGroup = new THREE.Group();

    // Foil cup base
    const baseGeo = new THREE.CylinderGeometry(0.65, 0.48, 0.6, 16);
    const baseMat = new THREE.MeshLambertMaterial({ color: 0xcca43b }); // Gold foil
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 0.3;
    base.castShadow = true;
    this.cupcakeGroup.add(base);

    // Swirled Whipped Cream Frosting
    const creamGeo = new THREE.SphereGeometry(0.6, 14, 14);
    const creamMat = new THREE.MeshLambertMaterial({ color: 0xff6b8b }); // Pink strawberry frosting
    const cream = new THREE.Mesh(creamGeo, creamMat);
    cream.position.y = 0.75;
    cream.scale.set(1, 0.85, 1);
    cream.castShadow = true;
    this.cupcakeGroup.add(cream);

    // Sprinkles on frosting
    const sprinkleGeo = new THREE.BoxGeometry(0.08, 0.08, 0.2);
    const sprinkleColors = [0xffffff, 0xffd166, 0x06d6a0, 0x118ab2];
    for (let s = 0; s < 8; s++) {
      const spMat = new THREE.MeshBasicMaterial({ color: sprinkleColors[s % sprinkleColors.length] });
      const sp = new THREE.Mesh(sprinkleGeo, spMat);
      sp.position.set(
        Math.sin(s * 0.8) * 0.4,
        0.9 + Math.cos(s * 0.5) * 0.15,
        Math.cos(s * 0.8) * 0.4
      );
      sp.rotation.set(s, s * 2, s * 0.5);
      this.cupcakeGroup.add(sp);
    }

    // Cherry on top
    const cherryGeo = new THREE.SphereGeometry(0.18, 12, 12);
    const cherryMat = new THREE.MeshLambertMaterial({ color: 0xd90429 });
    const cherry = new THREE.Mesh(cherryGeo, cherryMat);
    cherry.position.set(0, 1.3, 0);
    this.cupcakeGroup.add(cherry);

    this.mesh.add(this.cupcakeGroup);
  }

  /**
   * 4. Rolling Donut: Giant strawberry frosted donut that rolls toward player
   */
  createRollingDonut() {
    this.donutMesh = new THREE.Group();

    // Torus dough
    const doughGeo = new THREE.TorusGeometry(0.8, 0.35, 12, 24);
    const doughMat = new THREE.MeshLambertMaterial({ color: 0xe0a96d }); // Golden baked dough
    const dough = new THREE.Mesh(doughGeo, doughMat);
    dough.castShadow = true;
    this.donutMesh.add(dough);

    // Glazed Frosting layer on one side
    const frostingGeo = new THREE.TorusGeometry(0.8, 0.36, 12, 24, Math.PI * 1.8);
    const frostingMat = new THREE.MeshLambertMaterial({ color: 0xff3366 }); // Bright strawberry glaze
    const frosting = new THREE.Mesh(frostingGeo, frostingMat);
    frosting.position.z = 0.04;
    this.donutMesh.add(frosting);

    // Donut sprinkles
    const sprinkleGeo = new THREE.CylinderGeometry(0.04, 0.04, 0.2, 6);
    const colors = [0xffffff, 0xffd166, 0x06d6a0];
    for (let i = 0; i < 10; i++) {
      const angle = (i / 10) * Math.PI * 2;
      const spMat = new THREE.MeshBasicMaterial({ color: colors[i % colors.length] });
      const sp = new THREE.Mesh(sprinkleGeo, spMat);
      sp.position.set(Math.cos(angle) * 0.8, Math.sin(angle) * 0.8, 0.18);
      sp.rotation.z = angle + Math.PI / 4;
      this.donutMesh.add(sp);
    }

    this.donutMesh.position.y = 1.15;
    this.mesh.add(this.donutMesh);
  }

  /**
   * 5. Swinging Candy Cane: Swings pendulum-style over the lane
   */
  createSwingingCandyCane() {
    this.pivotGroup = new THREE.Group();
    this.pivotGroup.position.set(0, 3.5, 0); // High overhead pivot

    // Cane pole
    const poleGeo = new THREE.CylinderGeometry(0.12, 0.12, 2.6, 12);
    const poleMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const pole = new THREE.Mesh(poleGeo, poleMat);
    pole.position.y = -1.3;
    pole.castShadow = true;
    this.pivotGroup.add(pole);

    // Red candy stripes
    const ringGeo = new THREE.CylinderGeometry(0.13, 0.13, 0.18, 12);
    const redMat = new THREE.MeshLambertMaterial({ color: 0xd90429 });
    for (let r = 0; r < 5; r++) {
      const ring = new THREE.Mesh(ringGeo, redMat);
      ring.position.y = -0.5 - r * 0.45;
      this.pivotGroup.add(ring);
    }

    // Hook curve at bottom
    const hookGeo = new THREE.TorusGeometry(0.35, 0.12, 8, 16, Math.PI);
    const hook = new THREE.Mesh(hookGeo, redMat);
    hook.position.set(0.35, -2.6, 0);
    hook.rotation.z = Math.PI;
    this.pivotGroup.add(hook);

    this.mesh.add(this.pivotGroup);
  }

  /**
   * 6. Pop-up Cake: Emerges from the road as player approaches
   */
  createPopupCake() {
    this.cakeBody = new THREE.Group();

    // 2-tier celebration cake
    const tier1Geo = new THREE.CylinderGeometry(0.85, 0.85, 0.6, 18);
    const tier1Mat = new THREE.MeshLambertMaterial({ color: 0x5a3422 }); // Chocolate tier
    const tier1 = new THREE.Mesh(tier1Geo, tier1Mat);
    tier1.position.y = 0.3;
    tier1.castShadow = true;
    this.cakeBody.add(tier1);

    const tier2Geo = new THREE.CylinderGeometry(0.55, 0.55, 0.5, 18);
    const tier2Mat = new THREE.MeshLambertMaterial({ color: 0xffffff }); // Vanilla cream tier
    const tier2 = new THREE.Mesh(tier2Geo, tier2Mat);
    tier2.position.y = 0.85;
    tier2.castShadow = true;
    this.cakeBody.add(tier2);

    // Cake candle
    const candleGeo = new THREE.CylinderGeometry(0.06, 0.06, 0.3, 8);
    const candleMat = new THREE.MeshLambertMaterial({ color: 0xffd166 });
    const candle = new THREE.Mesh(candleGeo, candleMat);
    candle.position.y = 1.25;
    this.cakeBody.add(candle);

    // Flame
    const flameGeo = new THREE.SphereGeometry(0.08, 8, 8);
    const flameMat = new THREE.MeshBasicMaterial({ color: 0xff5400 });
    const flame = new THREE.Mesh(flameGeo, flameMat);
    flame.position.y = 1.45;
    this.cakeBody.add(flame);

    // Initially submerged beneath road
    this.cakeBody.position.y = -1.6;
    this.mesh.add(this.cakeBody);
  }

  /**
   * 7. Rotating Wafer Cookie: Giant cookie spinning on its axis
   */
  createRotatingCookie() {
    this.cookieMesh = new THREE.Group();

    const cookieGeo = new THREE.CylinderGeometry(0.85, 0.85, 0.25, 20);
    const cookieMat = new THREE.MeshLambertMaterial({ color: 0xcca43b }); // Golden butter cookie
    const cookie = new THREE.Mesh(cookieGeo, cookieMat);
    cookie.rotation.x = Math.PI / 2;
    cookie.castShadow = true;
    this.cookieMesh.add(cookie);

    // Chocolate chips
    const chipGeo = new THREE.SphereGeometry(0.1, 8, 8);
    const chipMat = new THREE.MeshLambertMaterial({ color: 0x3d1c06 });
    for (let c = 0; c < 6; c++) {
      const angle = (c / 6) * Math.PI * 2;
      const chip = new THREE.Mesh(chipGeo, chipMat);
      chip.position.set(Math.cos(angle) * 0.45, Math.sin(angle) * 0.45, 0.12);
      this.cookieMesh.add(chip);
    }

    this.cookieMesh.position.y = 1.0;
    this.mesh.add(this.cookieMesh);
  }

  /**
   * Update bounding box coordinates in world space.
   */
  updateBoundingBox() {
    this.mesh.updateMatrixWorld(true);
    this.boundingBox.setFromObject(this.mesh);
    // Fair collision margins
    this.boundingBox.min.x += 0.12;
    this.boundingBox.max.x -= 0.12;
    this.boundingBox.min.z += 0.12;
    this.boundingBox.max.z -= 0.12;
  }

  /**
   * Frame update with dynamic behaviors
   */
  update(speed, delta, playerZ = 0) {
    this.time += delta;
    this.mesh.position.z += speed * delta;

    // 1. Sliding behavior: Oscillates smoothly horizontally
    if (this.type === OBSTACLE_TYPES.SLIDING) {
      // Slides smoothly across ~1.8 units left/right
      const slideRange = this.options.slideRange || 1.8;
      const slideSpeed = this.options.slideSpeed || 2.2;
      this.mesh.position.x = this.baseX + Math.sin(this.time * slideSpeed) * slideRange;
      // Slight tilt while sliding
      if (this.jellyMesh) {
        this.jellyMesh.rotation.z = Math.cos(this.time * slideSpeed) * 0.15;
      }
    }

    // 2. Bouncing Cupcake behavior: Jumps up and down
    else if (this.type === OBSTACLE_TYPES.BOUNCING && this.cupcakeGroup) {
      const bounce = Math.abs(Math.sin(this.time * 4.5));
      this.cupcakeGroup.position.y = bounce * 1.6; // Bounces from 0 up to 1.6 units
      // Squash and stretch
      if (bounce < 0.1) {
        this.cupcakeGroup.scale.set(1.2, 0.8, 1.2);
      } else {
        this.cupcakeGroup.scale.set(0.9, 1.1, 0.9);
      }
    }

    // 3. Rolling Donut behavior: Spins forward while moving slightly faster
    else if (this.type === OBSTACLE_TYPES.ROLLING && this.donutMesh) {
      // Extra rolling speed towards player!
      this.mesh.position.z += 6 * delta;
      this.donutMesh.rotation.z -= delta * 6; // Rolling wheel rotation
    }

    // 4. Swinging Candy Cane behavior: Pendulum swing
    else if (this.type === OBSTACLE_TYPES.SWINGING && this.pivotGroup) {
      this.pivotGroup.rotation.z = Math.sin(this.time * 3.0) * 0.75;
    }

    // 5. Pop-up Cake behavior: Springs up when player gets within 30m
    else if (this.type === OBSTACLE_TYPES.POPUP && this.cakeBody) {
      const distToPlayer = Math.abs(this.mesh.position.z - playerZ);
      if (distToPlayer < 30 && !this.hasPoppedUp) {
        this.popupProgress = Math.min(1, this.popupProgress + delta * 6.5);
        this.cakeBody.position.y = -1.6 + this.popupProgress * 1.6;
        if (this.popupProgress >= 1) {
          this.hasPoppedUp = true;
        }
      }
    }

    // 6. Rotating Wafer Cookie
    else if (this.type === OBSTACLE_TYPES.ROTATING && this.cookieMesh) {
      this.cookieMesh.rotation.y += delta * 3.5;
    }

    this.updateBoundingBox();
  }
}

/**
 * Cake-Eating Monster (Enemy)
 * 
 * Special event enemy that rushes towards player down the road!
 * Cute, hungry, fluffy bakery beast with cake crumbs and frosting around mouth.
 */
class CakeEatingMonster {
  constructor(lane = 0, zPos = -95, laneWidth = 2.4) {
    this.lane = lane;
    this.laneWidth = laneWidth;
    this.zPos = zPos;
    this.speedBoost = 18; // Moves significantly faster than normal obstacles
    this.time = 0;
    this.active = true;

    this.mesh = new THREE.Group();
    this.boundingBox = new THREE.Box3();

    this.createMonsterModel();
    this.mesh.position.set(this.lane * this.laneWidth, 0, this.zPos);
    this.updateBoundingBox();
  }

  createMonsterModel() {
    this.bodyGroup = new THREE.Group();

    // 1. Chubby Fluffy Body (Sweet Berry Purple / Fuchsia)
    const bodyGeo = new THREE.SphereGeometry(0.85, 18, 18);
    const bodyMat = new THREE.MeshLambertMaterial({ color: 0x9b5de5 });
    const body = new THREE.Mesh(bodyGeo, bodyMat);
    body.position.y = 1.0;
    body.scale.set(1.1, 1.0, 1.2);
    body.castShadow = true;
    this.bodyGroup.add(body);

    // 2. Cute Little Donut Ears / Horns on top
    const earGeo = new THREE.ConeGeometry(0.2, 0.45, 10);
    const earMat = new THREE.MeshLambertMaterial({ color: 0xf15bb5 });

    const leftEar = new THREE.Mesh(earGeo, earMat);
    leftEar.position.set(-0.55, 1.85, 0);
    leftEar.rotation.z = 0.35;
    this.bodyGroup.add(leftEar);

    const rightEar = new THREE.Mesh(earGeo, earMat);
    rightEar.position.set(0.55, 1.85, 0);
    rightEar.rotation.z = -0.35;
    this.bodyGroup.add(rightEar);

    // 3. Huge Open Hungry Mouth (Chomping for cake!)
    const mouthGeo = new THREE.SphereGeometry(0.52, 14, 14, 0, Math.PI * 2, 0, Math.PI * 0.5);
    const mouthMat = new THREE.MeshLambertMaterial({ color: 0x4a0e17 }); // Dark mouth interior
    const mouth = new THREE.Mesh(mouthGeo, mouthMat);
    mouth.position.set(0, 0.92, 0.7);
    mouth.rotation.x = -Math.PI / 2;
    this.bodyGroup.add(mouth);

    // Cute hungry pink tongue
    const tongueGeo = new THREE.SphereGeometry(0.26, 10, 10);
    const tongueMat = new THREE.MeshLambertMaterial({ color: 0xff4d6d });
    this.tongue = new THREE.Mesh(tongueGeo, tongueMat);
    this.tongue.position.set(0, 0.78, 0.95);
    this.tongue.scale.set(1, 0.4, 1.3);
    this.bodyGroup.add(this.tongue);

    // Frosting & Cake Crumbs smeared around mouth!
    const frostingGeo = new THREE.TorusGeometry(0.5, 0.08, 8, 16);
    const frostingMat = new THREE.MeshLambertMaterial({ color: 0xffffff }); // Vanilla frosting smear
    const frosting = new THREE.Mesh(frostingGeo, frostingMat);
    frosting.position.set(0, 0.92, 0.85);
    this.bodyGroup.add(frosting);

    // Strawberry crumbs
    const crumbGeo = new THREE.SphereGeometry(0.06, 6, 6);
    const crumbMat = new THREE.MeshLambertMaterial({ color: 0xff0054 }); // Strawberry jam dots
    for (let c = 0; c < 5; c++) {
      const angle = c * 1.3;
      const crumb = new THREE.Mesh(crumbGeo, crumbMat);
      crumb.position.set(Math.cos(angle) * 0.45, 0.9 + Math.sin(angle) * 0.25, 0.92);
      this.bodyGroup.add(crumb);
    }

    // 4. Big Funny Googly Eyes
    const eyeWhiteGeo = new THREE.SphereGeometry(0.22, 12, 12);
    const eyeWhiteMat = new THREE.MeshLambertMaterial({ color: 0xffffff });

    const leftWhite = new THREE.Mesh(eyeWhiteGeo, eyeWhiteMat);
    leftWhite.position.set(-0.35, 1.5, 0.65);
    this.bodyGroup.add(leftWhite);

    const rightWhite = new THREE.Mesh(eyeWhiteGeo, eyeWhiteMat);
    rightWhite.position.set(0.35, 1.5, 0.65);
    this.bodyGroup.add(rightWhite);

    // Pupils (facing player direction +Z)
    const pupilGeo = new THREE.SphereGeometry(0.1, 10, 10);
    const pupilMat = new THREE.MeshBasicMaterial({ color: 0x111111 });

    const leftPupil = new THREE.Mesh(pupilGeo, pupilMat);
    leftPupil.position.set(-0.32, 1.5, 0.84);
    this.bodyGroup.add(leftPupil);

    const rightPupil = new THREE.Mesh(pupilGeo, pupilMat);
    rightPupil.position.set(0.32, 1.5, 0.84);
    this.bodyGroup.add(rightPupil);

    // 5. Running Paws
    const pawGeo = new THREE.SphereGeometry(0.22, 10, 10);
    const pawMat = new THREE.MeshLambertMaterial({ color: 0x7b2cbf });

    this.leftPaw = new THREE.Mesh(pawGeo, pawMat);
    this.leftPaw.position.set(-0.5, 0.2, 0);
    this.leftPaw.scale.set(1, 0.6, 1.4);
    this.bodyGroup.add(this.leftPaw);

    this.rightPaw = new THREE.Mesh(pawGeo, pawMat);
    this.rightPaw.position.set(0.5, 0.2, 0);
    this.rightPaw.scale.set(1, 0.6, 1.4);
    this.bodyGroup.add(this.rightPaw);

    this.mesh.add(this.bodyGroup);
  }

  updateBoundingBox() {
    this.mesh.updateMatrixWorld(true);
    this.boundingBox.setFromObject(this.mesh);
    this.boundingBox.min.x += 0.15;
    this.boundingBox.max.x -= 0.15;
  }

  update(speed, delta) {
    this.time += delta * 14;

    // Rush forward significantly faster than normal track speed!
    this.mesh.position.z += (speed + this.speedBoost) * delta;

    // Funny hungry running waddle & chomping mouth
    this.bodyGroup.position.y = Math.abs(Math.sin(this.time)) * 0.25;
    this.bodyGroup.rotation.z = Math.sin(this.time * 0.5) * 0.12;

    if (this.tongue) {
      this.tongue.scale.z = 1.2 + Math.sin(this.time) * 0.4;
    }
    if (this.leftPaw && this.rightPaw) {
      this.leftPaw.position.z = Math.sin(this.time) * 0.35;
      this.rightPaw.position.z = -Math.sin(this.time) * 0.35;
    }

    this.updateBoundingBox();
  }
}

/**
 * Manages spawning, difficulty progression, combinations, and monster events.
 */
class ObstacleManager {
  /**
   * @param {THREE.Scene} scene
   * @param {function} onWarningCallback - Called with (boolean) to show/hide warning
   */
  constructor(scene, onWarningCallback = null) {
    this.scene = scene;
    this.onWarning = onWarningCallback;
    this.obstacles = [];
    this.monster = null;

    this.spawnDistance = -85;
    this.despawnZ = 16;
    this.minSpacing = 26; // Base spacing, gets tighter as distance grows
    this.laneWidth = 2.4;

    // Monster spawning schedule
    this.monsterTimer = 0;
    this.monsterInterval = 25; // Seconds between potential monster attacks
    this.isWarningActive = false;
    this.warningCountdown = 0;
    this.pendingMonsterLane = 0;
  }

  /**
   * Spawns an obstacle or combination according to current distance difficulty.
   * @param {number} zPos - Z position to spawn
   * @param {number} distance - Current game distance in meters
   * @param {number} playerLane - Current player lane
   */
  spawnPattern(zPos, distance = 0, playerLane = 0) {
    const lanes = [-1, 0, 1];

    // Difficulty tiers:
    // Tier 1: 0 - 100m -> Mostly stationary hurdles, generous spacing
    // Tier 2: 100 - 300m -> Moving obstacles (sliding, bouncing, rolling)
    // Tier 3: 300m+ -> Combinations & pop-ups (always leave at least 1 safe lane)

    const isAdvanced = distance > 120;
    const canDoCombination = distance > 280 && Math.random() < 0.25;

    if (canDoCombination) {
      // Spawn combination: 2 obstacles, guaranteed 1 open lane!
      const safeLane = lanes[Math.floor(Math.random() * lanes.length)];
      const blockedLanes = lanes.filter(l => l !== safeLane);

      // Blocked lane 1
      const type1 = Math.random() < 0.6 ? OBSTACLE_TYPES.HURDLE : OBSTACLE_TYPES.POPUP;
      const obs1 = new Obstacle(type1, blockedLanes[0], zPos, this.laneWidth);
      this.scene.add(obs1.mesh);
      this.obstacles.push(obs1);

      // Blocked lane 2 (slightly offset or different type for fair reading)
      const type2 = OBSTACLE_TYPES.HURDLE;
      const obs2 = new Obstacle(type2, blockedLanes[1], zPos, this.laneWidth);
      this.scene.add(obs2.mesh);
      this.obstacles.push(obs2);

    } else {
      // Single obstacle in a chosen lane
      const chosenLane = lanes[Math.floor(Math.random() * lanes.length)];
      let type = OBSTACLE_TYPES.HURDLE;

      if (isAdvanced) {
        const roll = Math.random();
        if (roll < 0.35) {
          type = OBSTACLE_TYPES.HURDLE; // Keep 35% stationary for fairness
        } else if (roll < 0.55) {
          type = OBSTACLE_TYPES.SLIDING;
        } else if (roll < 0.70) {
          type = OBSTACLE_TYPES.BOUNCING;
        } else if (roll < 0.85) {
          type = OBSTACLE_TYPES.ROLLING;
        } else if (roll < 0.93) {
          type = OBSTACLE_TYPES.POPUP;
        } else {
          type = OBSTACLE_TYPES.SWINGING;
        }
      }

      const obstacle = new Obstacle(type, chosenLane, zPos, this.laneWidth);
      this.scene.add(obstacle.mesh);
      this.obstacles.push(obstacle);
    }
  }

  /**
   * Schedule or update the Cake-Eating Monster attack
   */
  updateMonsterEvent(speed, delta, distance, playerLane) {
    // Monster unlocked after 120m
    if (distance < 120) return;

    this.monsterTimer += delta;

    // Trigger Warning
    if (!this.monster && !this.isWarningActive && this.monsterTimer >= this.monsterInterval) {
      this.isWarningActive = true;
      this.warningCountdown = 2.4; // 2.4s warning banner before monster arrives!
      this.pendingMonsterLane = playerLane; // Attacks down the player's current lane!

      if (this.onWarning) {
        this.onWarning(true);
      }
      if (window.soundEngine) {
        window.soundEngine.playWarning();
      }
    }

    // Warning countdown
    if (this.isWarningActive) {
      this.warningCountdown -= delta;
      if (this.warningCountdown <= 0) {
        this.isWarningActive = false;
        if (this.onWarning) {
          this.onWarning(false);
        }

        // Spawn Monster!
        this.monster = new CakeEatingMonster(this.pendingMonsterLane, -110, this.laneWidth);
        this.scene.add(this.monster.mesh);

        if (window.soundEngine) {
          window.soundEngine.playMonsterRoar();
        }

        // Reset timer for next attack
        this.monsterTimer = 0;
        this.monsterInterval = Math.max(16, 28 - (distance / 200) * 2);
      }
    }

    // Update active monster
    if (this.monster) {
      this.monster.update(speed, delta);

      // Remove after passing camera
      if (this.monster.mesh.position.z > this.despawnZ) {
        this.scene.remove(this.monster.mesh);
        this.monster = null;
      }
    }
  }

  /**
   * Main update loop
   */
  update(speed, delta, distance = 0, player) {
    const playerZ = player ? player.mesh.position.z : 0;
    const playerLane = player ? player.currentLane : 0;

    // 1. Move & update existing obstacles
    for (let i = this.obstacles.length - 1; i >= 0; i--) {
      const obs = this.obstacles[i];
      obs.update(speed, delta, playerZ);

      if (obs.mesh.position.z > this.despawnZ) {
        this.scene.remove(obs.mesh);
        this.obstacles.splice(i, 1);
      }
    }

    // 2. Adjust dynamic spacing based on distance (progressively tighter)
    this.minSpacing = Math.max(17, 27 - (distance / 250) * 4);

    // 3. Spawn new obstacles ahead
    let furthestZ = 0;
    if (this.obstacles.length > 0) {
      furthestZ = Math.min(...this.obstacles.map(o => o.mesh.position.z));
    }

    if (furthestZ > this.spawnDistance + this.minSpacing) {
      const nextZ = Math.min(furthestZ - this.minSpacing, this.spawnDistance);
      this.spawnPattern(nextZ, distance, playerLane);
    }

    // 4. Update Monster special event
    this.updateMonsterEvent(speed, delta, distance, playerLane);
  }

  /**
   * Collision check for both obstacles and the monster
   */
  checkCollision(player) {
    // Check monster collision
    if (this.monster) {
      if (Math.abs(this.monster.mesh.position.z - player.mesh.position.z) < 2.5) {
        if (this.monster.boundingBox.intersectsBox(player.boundingBox)) {
          return true;
        }
      }
    }

    // Check normal obstacles
    for (let obs of this.obstacles) {
      if (Math.abs(obs.mesh.position.z - player.mesh.position.z) < 2.8) {
        if (obs.boundingBox.intersectsBox(player.boundingBox)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Clear everything for new game
   */
  reset() {
    for (let obs of this.obstacles) {
      this.scene.remove(obs.mesh);
    }
    this.obstacles = [];

    if (this.monster) {
      this.scene.remove(this.monster.mesh);
      this.monster = null;
    }

    this.isWarningActive = false;
    this.monsterTimer = 0;
    if (this.onWarning) {
      this.onWarning(false);
    }

    // Pre-populate initial hurdles safely ahead
    this.spawnPattern(-38, 0, 0);
    this.spawnPattern(-70, 0, 0);
    this.spawnPattern(-100, 0, 0);
  }
}
