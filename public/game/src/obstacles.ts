/**
 * Sweet Run - Dynamic Obstacles & Cake-Eating Monster (src/obstacles.ts)
 */

import * as THREE from 'three';
import { soundEngine } from './audio';
import { Player } from './player';

export const OBSTACLE_TYPES = {
  HURDLE: 'hurdle',
  SLIDING: 'sliding',
  BOUNCING: 'bouncing',
  ROLLING: 'rolling',
  SWINGING: 'swinging',
  POPUP: 'popup',
  ROTATING: 'rotating',
  HIGH_BARRIER: 'high_barrier'
} as const;

export type ObstacleType = typeof OBSTACLE_TYPES[keyof typeof OBSTACLE_TYPES];

export interface ObstacleOptions {
  slideRange?: number;
  slideSpeed?: number;
}

export class Obstacle {
  public type: ObstacleType;
  public lane: number;
  public baseX: number;
  public zPos: number;
  public laneWidth: number;
  public options: ObstacleOptions;
  public time: number;
  public active: boolean;

  public mesh: THREE.Group;
  public boundingBox: THREE.Box3;

  public hasPoppedUp: boolean;
  public popupProgress: number;

  public jellyMesh?: THREE.Mesh;
  public cupcakeGroup?: THREE.Group;
  public donutMesh?: THREE.Group;
  public pivotGroup?: THREE.Group;
  public cakeBody?: THREE.Group;
  public cookieMesh?: THREE.Group;

  constructor(
    type: ObstacleType = OBSTACLE_TYPES.HURDLE,
    lane: number = 0,
    zPos: number = -80,
    laneWidth: number = 2.4,
    options: ObstacleOptions = {}
  ) {
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
      case OBSTACLE_TYPES.HIGH_BARRIER:
        this.createHighBarrier();
        break;
      case OBSTACLE_TYPES.HURDLE:
      default:
        this.createChocolateHurdle();
        break;
    }
  }

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

    const segmentGeo = new THREE.BoxGeometry(0.65, 0.15, 0.65);
    const segmentMat = new THREE.MeshLambertMaterial({ color: 0x5a3422 });
    const leftChunk = new THREE.Mesh(segmentGeo, segmentMat);
    leftChunk.position.set(-0.45, blockHeight + 0.05, 0);
    this.mesh.add(leftChunk);

    const rightChunk = new THREE.Mesh(segmentGeo, segmentMat);
    rightChunk.position.set(0.45, blockHeight + 0.05, 0);
    this.mesh.add(rightChunk);

    const icingGeo = new THREE.CylinderGeometry(0.04, 0.04, 1.5, 8);
    const icingMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
    const icing1 = new THREE.Mesh(icingGeo, icingMat);
    icing1.rotation.z = Math.PI / 2;
    icing1.position.set(0, blockHeight + 0.12, 0);
    this.mesh.add(icing1);

    const cherryGeo = new THREE.SphereGeometry(0.18, 12, 12);
    const cherryMat = new THREE.MeshLambertMaterial({ color: 0xd90429 });
    const cherry = new THREE.Mesh(cherryGeo, cherryMat);
    cherry.position.set(0, blockHeight + 0.22, 0);
    this.mesh.add(cherry);
  }

  createSlidingJelly() {
    const geo = new THREE.CylinderGeometry(0.7, 0.8, 1.0, 16);
    const mat = new THREE.MeshLambertMaterial({
      color: 0x00c49f,
      transparent: true,
      opacity: 0.88
    });
    this.jellyMesh = new THREE.Mesh(geo, mat);
    this.jellyMesh.position.y = 0.5;
    this.jellyMesh.castShadow = true;
    this.mesh.add(this.jellyMesh);

    const crystalGeo = new THREE.DodecahedronGeometry(0.15);
    const crystalMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const crystal = new THREE.Mesh(crystalGeo, crystalMat);
    crystal.position.set(0, 1.05, 0);
    this.mesh.add(crystal);
  }

  createBouncingCupcake() {
    this.cupcakeGroup = new THREE.Group();

    const baseGeo = new THREE.CylinderGeometry(0.65, 0.48, 0.6, 16);
    const baseMat = new THREE.MeshLambertMaterial({ color: 0xcca43b });
    const base = new THREE.Mesh(baseGeo, baseMat);
    base.position.y = 0.3;
    base.castShadow = true;
    this.cupcakeGroup.add(base);

    const creamGeo = new THREE.SphereGeometry(0.6, 14, 14);
    const creamMat = new THREE.MeshLambertMaterial({ color: 0xff6b8b });
    const cream = new THREE.Mesh(creamGeo, creamMat);
    cream.position.y = 0.75;
    cream.scale.set(1, 0.85, 1);
    cream.castShadow = true;
    this.cupcakeGroup.add(cream);

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

    const cherryGeo = new THREE.SphereGeometry(0.18, 12, 12);
    const cherryMat = new THREE.MeshLambertMaterial({ color: 0xd90429 });
    const cherry = new THREE.Mesh(cherryGeo, cherryMat);
    cherry.position.set(0, 1.3, 0);
    this.cupcakeGroup.add(cherry);

    this.mesh.add(this.cupcakeGroup);
  }

  createRollingDonut() {
    this.donutMesh = new THREE.Group();

    const doughGeo = new THREE.TorusGeometry(0.8, 0.35, 12, 24);
    const doughMat = new THREE.MeshLambertMaterial({ color: 0xe0a96d });
    const dough = new THREE.Mesh(doughGeo, doughMat);
    dough.castShadow = true;
    this.donutMesh.add(dough);

    const frostingGeo = new THREE.TorusGeometry(0.8, 0.36, 12, 24, Math.PI * 1.8);
    const frostingMat = new THREE.MeshLambertMaterial({ color: 0xff3366 });
    const frosting = new THREE.Mesh(frostingGeo, frostingMat);
    frosting.position.z = 0.04;
    this.donutMesh.add(frosting);

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

  createSwingingCandyCane() {
    this.pivotGroup = new THREE.Group();
    this.pivotGroup.position.set(0, 3.5, 0);

    const poleGeo = new THREE.CylinderGeometry(0.12, 0.12, 2.6, 12);
    const poleMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const pole = new THREE.Mesh(poleGeo, poleMat);
    pole.position.y = -1.3;
    pole.castShadow = true;
    this.pivotGroup.add(pole);

    const ringGeo = new THREE.CylinderGeometry(0.13, 0.13, 0.18, 12);
    const redMat = new THREE.MeshLambertMaterial({ color: 0xd90429 });
    for (let r = 0; r < 5; r++) {
      const ring = new THREE.Mesh(ringGeo, redMat);
      ring.position.y = -0.5 - r * 0.45;
      this.pivotGroup.add(ring);
    }

    const hookGeo = new THREE.TorusGeometry(0.35, 0.12, 8, 16, Math.PI);
    const hook = new THREE.Mesh(hookGeo, redMat);
    hook.position.set(0.35, -2.6, 0);
    hook.rotation.z = Math.PI;
    this.pivotGroup.add(hook);

    this.mesh.add(this.pivotGroup);
  }

  createPopupCake() {
    this.cakeBody = new THREE.Group();

    const tier1Geo = new THREE.CylinderGeometry(0.85, 0.85, 0.6, 18);
    const tier1Mat = new THREE.MeshLambertMaterial({ color: 0x5a3422 });
    const tier1 = new THREE.Mesh(tier1Geo, tier1Mat);
    tier1.position.y = 0.3;
    tier1.castShadow = true;
    this.cakeBody.add(tier1);

    const tier2Geo = new THREE.CylinderGeometry(0.55, 0.55, 0.5, 18);
    const tier2Mat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const tier2 = new THREE.Mesh(tier2Geo, tier2Mat);
    tier2.position.y = 0.85;
    tier2.castShadow = true;
    this.cakeBody.add(tier2);

    const candleGeo = new THREE.CylinderGeometry(0.06, 0.06, 0.3, 8);
    const candleMat = new THREE.MeshLambertMaterial({ color: 0xffd166 });
    const candle = new THREE.Mesh(candleGeo, candleMat);
    candle.position.y = 1.25;
    this.cakeBody.add(candle);

    const flameGeo = new THREE.SphereGeometry(0.08, 8, 8);
    const flameMat = new THREE.MeshBasicMaterial({ color: 0xff5400 });
    const flame = new THREE.Mesh(flameGeo, flameMat);
    flame.position.y = 1.45;
    this.cakeBody.add(flame);

    this.cakeBody.position.y = -1.6;
    this.mesh.add(this.cakeBody);
  }

  createRotatingCookie() {
    this.cookieMesh = new THREE.Group();

    const cookieGeo = new THREE.CylinderGeometry(0.85, 0.85, 0.25, 20);
    const cookieMat = new THREE.MeshLambertMaterial({ color: 0xcca43b });
    const cookie = new THREE.Mesh(cookieGeo, cookieMat);
    cookie.rotation.x = Math.PI / 2;
    cookie.castShadow = true;
    this.cookieMesh.add(cookie);

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

  createHighBarrier() {
    const barrierGroup = new THREE.Group();

    // 1. Two side candy cane pillars at the lane edges
    const postGeo = new THREE.CylinderGeometry(0.12, 0.12, 2.7, 10);
    const postMat = new THREE.MeshLambertMaterial({ color: 0xffffff });

    const leftPost = new THREE.Mesh(postGeo, postMat);
    leftPost.position.set(-1.08, 1.35, 0);
    leftPost.castShadow = true;
    barrierGroup.add(leftPost);

    const rightPost = new THREE.Mesh(postGeo, postMat);
    rightPost.position.set(1.08, 1.35, 0);
    rightPost.castShadow = true;
    barrierGroup.add(rightPost);

    // Red spiral rings on side posts
    const ringGeo = new THREE.CylinderGeometry(0.13, 0.13, 0.2, 10);
    const ringMat = new THREE.MeshLambertMaterial({ color: 0xd90429 });
    for (let i = 0; i < 4; i++) {
      const leftRing = new THREE.Mesh(ringGeo, ringMat);
      leftRing.position.set(-1.08, 0.45 + i * 0.6, 0);
      barrierGroup.add(leftRing);

      const rightRing = new THREE.Mesh(ringGeo, ringMat);
      rightRing.position.set(1.08, 0.45 + i * 0.6, 0);
      barrierGroup.add(rightRing);
    }

    // 2. Thick High Overhead Frosted Candy Crossbar (from y = 1.05 to y = 2.45)
    const beamGeo = new THREE.BoxGeometry(2.35, 1.25, 0.5);
    const beamMat = new THREE.MeshLambertMaterial({ color: 0x5a3422 });
    const beam = new THREE.Mesh(beamGeo, beamMat);
    beam.position.set(0, 1.85, 0);
    beam.castShadow = true;
    barrierGroup.add(beam);

    // Strawberry icing top glaze
    const glazeGeo = new THREE.BoxGeometry(2.4, 0.25, 0.54);
    const glazeMat = new THREE.MeshLambertMaterial({ color: 0xff3366 });
    const glaze = new THREE.Mesh(glazeGeo, glazeMat);
    glaze.position.set(0, 2.5, 0);
    barrierGroup.add(glaze);

    // Hanging rock candy / peppermint teeth (protruding down to y = 0.82)
    const toothGeo = new THREE.ConeGeometry(0.13, 0.38, 8);
    toothGeo.rotateX(Math.PI);
    const toothMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    for (let t = -3; t <= 3; t++) {
      const tooth = new THREE.Mesh(toothGeo, toothMat);
      tooth.position.set(t * 0.32, 1.06, 0.05);
      barrierGroup.add(tooth);
    }

    // Glowing Neon Downward Arrow / Caution badge in center
    const signBoardGeo = new THREE.BoxGeometry(1.3, 0.45, 0.1);
    const signBoardMat = new THREE.MeshLambertMaterial({ color: 0xffd166 });
    const signBoard = new THREE.Mesh(signBoardGeo, signBoardMat);
    signBoard.position.set(0, 1.85, 0.28);
    barrierGroup.add(signBoard);

    // Down arrows on sign indicating to slide
    const arrowGeo = new THREE.ConeGeometry(0.11, 0.22, 6);
    const arrowMat = new THREE.MeshBasicMaterial({ color: 0xd90429 });
    for (let a = -1; a <= 1; a++) {
      const arrow = new THREE.Mesh(arrowGeo, arrowMat);
      arrow.rotation.z = Math.PI;
      arrow.position.set(a * 0.35, 1.85, 0.35);
      barrierGroup.add(arrow);
    }

    this.mesh.add(barrierGroup);
  }

  updateBoundingBox() {
    this.mesh.updateMatrixWorld(true);

    if (this.type === OBSTACLE_TYPES.HIGH_BARRIER) {
      // Elevated barrier: blocks heights between 0.80m and 2.7m across the lane.
      // Jumping hits it directly (cannot jump over!); only sliding (height <= 0.65m) fits under the gap!
      this.boundingBox.min.set(
        this.mesh.position.x - 0.95,
        0.80,
        this.mesh.position.z - 0.35
      );
      this.boundingBox.max.set(
        this.mesh.position.x + 0.95,
        2.7,
        this.mesh.position.z + 0.35
      );
    } else {
      this.boundingBox.setFromObject(this.mesh);
      this.boundingBox.min.x += 0.12;
      this.boundingBox.max.x -= 0.12;
      this.boundingBox.min.z += 0.12;
      this.boundingBox.max.z -= 0.12;
    }
  }

  update(speed: number, delta: number, playerZ: number = 0) {
    this.time += delta;
    this.mesh.position.z += speed * delta;

    if (this.type === OBSTACLE_TYPES.SLIDING) {
      const slideRange = this.options.slideRange || 1.8;
      const slideSpeed = this.options.slideSpeed || 2.2;
      this.mesh.position.x = this.baseX + Math.sin(this.time * slideSpeed) * slideRange;
      if (this.jellyMesh) {
        this.jellyMesh.rotation.z = Math.cos(this.time * slideSpeed) * 0.15;
      }
    } else if (this.type === OBSTACLE_TYPES.BOUNCING && this.cupcakeGroup) {
      const bounce = Math.abs(Math.sin(this.time * 4.5));
      this.cupcakeGroup.position.y = bounce * 1.6;
      if (bounce < 0.1) {
        this.cupcakeGroup.scale.set(1.2, 0.8, 1.2);
      } else {
        this.cupcakeGroup.scale.set(0.9, 1.1, 0.9);
      }
    } else if (this.type === OBSTACLE_TYPES.ROLLING && this.donutMesh) {
      this.mesh.position.z += 6 * delta;
      this.donutMesh.rotation.z -= delta * 6;
    } else if (this.type === OBSTACLE_TYPES.SWINGING && this.pivotGroup) {
      this.pivotGroup.rotation.z = Math.sin(this.time * 3.0) * 0.75;
    } else if (this.type === OBSTACLE_TYPES.POPUP && this.cakeBody) {
      const distToPlayer = Math.abs(this.mesh.position.z - playerZ);
      if (distToPlayer < 30 && !this.hasPoppedUp) {
        this.popupProgress = Math.min(1, this.popupProgress + delta * 6.5);
        this.cakeBody.position.y = -1.6 + this.popupProgress * 1.6;
        if (this.popupProgress >= 1) {
          this.hasPoppedUp = true;
        }
      }
    } else if (this.type === OBSTACLE_TYPES.ROTATING && this.cookieMesh) {
      this.cookieMesh.rotation.y += delta * 3.5;
    }

    this.updateBoundingBox();
  }
}

export class CakeEatingMonster {
  public lane: number;
  public laneWidth: number;
  public zPos: number;
  public speedBoost: number = 18;
  public time: number = 0;
  public active: boolean = true;

  public mesh: THREE.Group;
  public boundingBox: THREE.Box3;
  public bodyGroup!: THREE.Group;
  public tongue?: THREE.Mesh;
  public leftPaw?: THREE.Mesh;
  public rightPaw?: THREE.Mesh;

  constructor(lane: number = 0, zPos: number = -95, laneWidth: number = 2.4) {
    this.lane = lane;
    this.laneWidth = laneWidth;
    this.zPos = zPos;

    this.mesh = new THREE.Group();
    this.boundingBox = new THREE.Box3();

    this.createMonsterModel();
    this.mesh.position.set(this.lane * this.laneWidth, 0, this.zPos);
    this.updateBoundingBox();
  }

  createMonsterModel() {
    this.bodyGroup = new THREE.Group();

    const bodyGeo = new THREE.SphereGeometry(0.85, 18, 18);
    const bodyMat = new THREE.MeshLambertMaterial({ color: 0x9b5de5 });
    const body = new THREE.Mesh(bodyGeo, bodyMat);
    body.position.y = 1.0;
    body.scale.set(1.1, 1.0, 1.2);
    body.castShadow = true;
    this.bodyGroup.add(body);

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

    const mouthGeo = new THREE.SphereGeometry(0.52, 14, 14, 0, Math.PI * 2, 0, Math.PI * 0.5);
    const mouthMat = new THREE.MeshLambertMaterial({ color: 0x4a0e17 });
    const mouth = new THREE.Mesh(mouthGeo, mouthMat);
    mouth.position.set(0, 0.92, 0.7);
    mouth.rotation.x = -Math.PI / 2;
    this.bodyGroup.add(mouth);

    const tongueGeo = new THREE.SphereGeometry(0.26, 10, 10);
    const tongueMat = new THREE.MeshLambertMaterial({ color: 0xff4d6d });
    this.tongue = new THREE.Mesh(tongueGeo, tongueMat);
    this.tongue.position.set(0, 0.78, 0.95);
    this.tongue.scale.set(1, 0.4, 1.3);
    this.bodyGroup.add(this.tongue);

    const frostingGeo = new THREE.TorusGeometry(0.5, 0.08, 8, 16);
    const frostingMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const frosting = new THREE.Mesh(frostingGeo, frostingMat);
    frosting.position.set(0, 0.92, 0.85);
    this.bodyGroup.add(frosting);

    const crumbGeo = new THREE.SphereGeometry(0.06, 6, 6);
    const crumbMat = new THREE.MeshLambertMaterial({ color: 0xff0054 });
    for (let c = 0; c < 5; c++) {
      const angle = c * 1.3;
      const crumb = new THREE.Mesh(crumbGeo, crumbMat);
      crumb.position.set(Math.cos(angle) * 0.45, 0.9 + Math.sin(angle) * 0.25, 0.92);
      this.bodyGroup.add(crumb);
    }

    const eyeWhiteGeo = new THREE.SphereGeometry(0.22, 12, 12);
    const eyeWhiteMat = new THREE.MeshLambertMaterial({ color: 0xffffff });

    const leftWhite = new THREE.Mesh(eyeWhiteGeo, eyeWhiteMat);
    leftWhite.position.set(-0.35, 1.5, 0.65);
    this.bodyGroup.add(leftWhite);

    const rightWhite = new THREE.Mesh(eyeWhiteGeo, eyeWhiteMat);
    rightWhite.position.set(0.35, 1.5, 0.65);
    this.bodyGroup.add(rightWhite);

    const pupilGeo = new THREE.SphereGeometry(0.1, 10, 10);
    const pupilMat = new THREE.MeshBasicMaterial({ color: 0x111111 });

    const leftPupil = new THREE.Mesh(pupilGeo, pupilMat);
    leftPupil.position.set(-0.32, 1.5, 0.84);
    this.bodyGroup.add(leftPupil);

    const rightPupil = new THREE.Mesh(pupilGeo, pupilMat);
    rightPupil.position.set(0.32, 1.5, 0.84);
    this.bodyGroup.add(rightPupil);

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

  update(speed: number, delta: number) {
    this.time += delta * 14;
    this.mesh.position.z += (speed + this.speedBoost) * delta;

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

export class ObstacleManager {
  public scene: THREE.Scene;
  public onWarning?: (show: boolean) => void;
  public obstacles: Obstacle[] = [];
  public monster: CakeEatingMonster | null = null;

  public spawnDistance: number = -85;
  public despawnZ: number = 16;
  public minSpacing: number = 26;
  public laneWidth: number = 2.4;

  public monsterTimer: number = 0;
  public monsterInterval: number = 25;
  public isWarningActive: boolean = false;
  public warningCountdown: number = 0;
  public pendingMonsterLane: number = 0;

  constructor(scene: THREE.Scene, onWarningCallback?: (show: boolean) => void) {
    this.scene = scene;
    this.onWarning = onWarningCallback;
  }

  spawnPattern(zPos: number, distance: number = 0, _playerLane: number = 0) {
    const lanes = [-1, 0, 1];
    const isAdvanced = distance > 120;
    const canDoCombination = distance > 280 && Math.random() < 0.25;

    if (canDoCombination) {
      const safeLane = lanes[Math.floor(Math.random() * lanes.length)];
      const blockedLanes = lanes.filter(l => l !== safeLane);

      const roll1 = Math.random();
      const type1 = roll1 < 0.4 ? OBSTACLE_TYPES.HIGH_BARRIER : (roll1 < 0.7 ? OBSTACLE_TYPES.HURDLE : OBSTACLE_TYPES.POPUP);
      const obs1 = new Obstacle(type1, blockedLanes[0], zPos, this.laneWidth);
      this.scene.add(obs1.mesh);
      this.obstacles.push(obs1);

      const type2 = Math.random() < 0.35 ? OBSTACLE_TYPES.HIGH_BARRIER : OBSTACLE_TYPES.HURDLE;
      const obs2 = new Obstacle(type2, blockedLanes[1], zPos, this.laneWidth);
      this.scene.add(obs2.mesh);
      this.obstacles.push(obs2);
    } else {
      const chosenLane = lanes[Math.floor(Math.random() * lanes.length)];
      let type: ObstacleType = OBSTACLE_TYPES.HURDLE;

      if (distance > 30) {
        const roll = Math.random();
        if (roll < 0.28) {
          type = OBSTACLE_TYPES.HIGH_BARRIER; // 28% High overhead barrier (slide to pass!)
        } else if (roll < 0.48) {
          type = OBSTACLE_TYPES.HURDLE;
        } else if (roll < 0.62) {
          type = OBSTACLE_TYPES.SLIDING;
        } else if (roll < 0.74) {
          type = OBSTACLE_TYPES.BOUNCING;
        } else if (roll < 0.85) {
          type = OBSTACLE_TYPES.ROLLING;
        } else if (roll < 0.93) {
          type = OBSTACLE_TYPES.POPUP;
        } else {
          type = OBSTACLE_TYPES.SWINGING;
        }
      } else {
        type = Math.random() < 0.35 ? OBSTACLE_TYPES.HIGH_BARRIER : OBSTACLE_TYPES.HURDLE;
      }

      const obstacle = new Obstacle(type, chosenLane, zPos, this.laneWidth);
      this.scene.add(obstacle.mesh);
      this.obstacles.push(obstacle);
    }
  }

  updateMonsterEvent(speed: number, delta: number, distance: number, playerLane: number) {
    if (distance < 120) return;

    this.monsterTimer += delta;

    if (!this.monster && !this.isWarningActive && this.monsterTimer >= this.monsterInterval) {
      this.isWarningActive = true;
      this.warningCountdown = 2.4;
      this.pendingMonsterLane = playerLane;

      if (this.onWarning) {
        this.onWarning(true);
      }
      soundEngine.playWarning();
    }

    if (this.isWarningActive) {
      this.warningCountdown -= delta;
      if (this.warningCountdown <= 0) {
        this.isWarningActive = false;
        if (this.onWarning) {
          this.onWarning(false);
        }

        this.monster = new CakeEatingMonster(this.pendingMonsterLane, -110, this.laneWidth);
        this.scene.add(this.monster.mesh);
        soundEngine.playMonsterRoar();

        this.monsterTimer = 0;
        this.monsterInterval = Math.max(16, 28 - (distance / 200) * 2);
      }
    }

    if (this.monster) {
      this.monster.update(speed, delta);

      if (this.monster.mesh.position.z > this.despawnZ) {
        this.scene.remove(this.monster.mesh);
        this.monster = null;
      }
    }
  }

  update(speed: number, delta: number, distance: number = 0, player?: Player) {
    const playerZ = player ? player.mesh.position.z : 0;
    const playerLane = player ? player.currentLane : 0;

    for (let i = this.obstacles.length - 1; i >= 0; i--) {
      const obs = this.obstacles[i];
      obs.update(speed, delta, playerZ);

      if (obs.mesh.position.z > this.despawnZ) {
        this.scene.remove(obs.mesh);
        this.obstacles.splice(i, 1);
      }
    }

    this.minSpacing = Math.max(17, 27 - (distance / 250) * 4);

    let furthestZ = 0;
    if (this.obstacles.length > 0) {
      furthestZ = Math.min(...this.obstacles.map(o => o.mesh.position.z));
    }

    if (furthestZ > this.spawnDistance + this.minSpacing) {
      const nextZ = Math.min(furthestZ - this.minSpacing, this.spawnDistance);
      this.spawnPattern(nextZ, distance, playerLane);
    }

    this.updateMonsterEvent(speed, delta, distance, playerLane);
  }

  checkCollision(player: Player, onShieldAbsorb?: () => void): boolean {
    if (this.monster) {
      if (Math.abs(this.monster.mesh.position.z - player.mesh.position.z) < 2.5) {
        if (this.monster.boundingBox.intersectsBox(player.boundingBox)) {
          if (player.isShielded) {
            // Shield absorbs monster hit: push monster back
            this.scene.remove(this.monster.mesh);
            this.monster = null;
            if (onShieldAbsorb) onShieldAbsorb();
            soundEngine.playShieldPop();
            return false;
          }
          return true;
        }
      }
    }

    for (let i = this.obstacles.length - 1; i >= 0; i--) {
      const obs = this.obstacles[i];
      if (Math.abs(obs.mesh.position.z - player.mesh.position.z) < 2.8) {
        if (obs.boundingBox.intersectsBox(player.boundingBox)) {
          if (player.isShielded) {
            // Shield shatters the obstacle into sugar dust!
            this.scene.remove(obs.mesh);
            this.obstacles.splice(i, 1);
            if (onShieldAbsorb) onShieldAbsorb();
            soundEngine.playShieldPop();
            return false;
          }
          return true;
        }
      }
    }

    return false;
  }

  reset() {
    for (const obs of this.obstacles) {
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

    this.spawnPattern(-38, 0, 0);
    this.spawnPattern(-70, 0, 0);
    this.spawnPattern(-100, 0, 0);
  }
}
