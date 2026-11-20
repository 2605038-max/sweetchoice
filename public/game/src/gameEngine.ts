/**
 * Sweet Run - 3D Game Engine (src/gameEngine.ts)
 */

import * as THREE from 'three';
import { Player } from './player';
import { ObstacleManager } from './obstacles';
import { CollectibleManager, Collectible } from './collectibles';
import { soundEngine } from './audio';
import { GameMetrics, CharacterStyle, PowerUpType, ActivePowerUp } from './types';

export interface GameEngineCallbacks {
  onMetricsUpdate: (metrics: GameMetrics) => void;
  onWarning: (show: boolean) => void;
  onPickup: (item: Collectible) => void;
  onGameOver: () => void;
}

export class GameEngine {
  public canvas: HTMLCanvasElement;
  public callbacks: GameEngineCallbacks;

  public isPlaying: boolean = false;
  public isPaused: boolean = false;
  public isGameOver: boolean = false;

  public baseSpeed: number = 18;
  public currentSpeed: number = 18;
  public maxSpeed: number = 31;
  public distance: number = 0;
  public score: number = 0;
  public treatsCount: number = 0;

  // Active power-up trackers
  public activePowerUps: Map<PowerUpType, { duration: number; maxDuration: number }> = new Map();

  public scene!: THREE.Scene;
  public camera!: THREE.PerspectiveCamera;
  public renderer!: THREE.WebGLRenderer;
  public dirLight!: THREE.DirectionalLight;

  public cameraDefaultPos = new THREE.Vector3(0, 3.8, 6.2);
  public cameraLookAt = new THREE.Vector3(0, 1.2, -6);

  public trackSegments: THREE.Group[] = [];
  public segmentLength: number = 40;
  public totalSegments: number = 5;
  public roadWidth: number = 8.0;

  public bakerySegments: THREE.Group[] = [];
  public bakerySegmentLength: number = 60;
  public totalBakerySegments: number = 4;
  public bakerySpeedMultiplier: number = 0.62;
  public bakeryGroup!: THREE.Group;
  public swingingSigns: THREE.Group[] = [];

  public clouds: THREE.Group[] = [];

  public player!: Player;
  public obstacleManager!: ObstacleManager;
  public collectibleManager!: CollectibleManager;

  public clock: THREE.Clock;
  public animFrameId: number | null = null;
  public envTimer: number = 0;

  constructor(canvas: HTMLCanvasElement, callbacks: GameEngineCallbacks) {
    this.canvas = canvas;
    this.callbacks = callbacks;
    this.clock = new THREE.Clock();

    this.initThree();
    this.initLights();
    this.initTrack();
    this.initBakeryTown();
    this.initCloudsAndAtmosphere();
    this.initEntities();

    this.animate = this.animate.bind(this);
    this.onWindowResize = this.onWindowResize.bind(this);
    window.addEventListener('resize', this.onWindowResize);

    this.animFrameId = requestAnimationFrame(this.animate);
  }

  initThree() {
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0xffe6f0);
    this.scene.fog = new THREE.FogExp2(0xffe6f0, 0.010);

    const width = this.canvas.parentElement ? this.canvas.parentElement.clientWidth : window.innerWidth;
    const height = this.canvas.parentElement ? this.canvas.parentElement.clientHeight : window.innerHeight;
    const aspect = width / height;

    this.camera = new THREE.PerspectiveCamera(58, aspect, 0.1, 350);
    this.camera.position.copy(this.cameraDefaultPos);
    this.camera.lookAt(this.cameraLookAt);

    this.renderer = new THREE.WebGLRenderer({
      canvas: this.canvas,
      antialias: true,
      powerPreference: 'high-performance'
    });
    this.renderer.setSize(width, height);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  }

  initLights() {
    const ambientLight = new THREE.AmbientLight(0xfff5f8, 0.8);
    this.scene.add(ambientLight);

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

    const hemiLight = new THREE.HemisphereLight(0xfff0f5, 0xffd1dc, 0.4);
    this.scene.add(hemiLight);
  }

  initTrack() {
    this.trackSegments = [];
    for (let i = 0; i < this.totalSegments; i++) {
      const segment = this.createTrackSegment();
      segment.position.z = -i * this.segmentLength + 10;
      this.scene.add(segment);
      this.trackSegments.push(segment);
    }
  }

  createTrackSegment(): THREE.Group {
    const segment = new THREE.Group();

    // Biscuit Road
    const roadGeo = new THREE.PlaneGeometry(this.roadWidth, this.segmentLength);
    const roadMat = new THREE.MeshLambertMaterial({
      color: 0xf5caa6,
      side: THREE.DoubleSide
    });
    const road = new THREE.Mesh(roadGeo, roadMat);
    road.rotation.x = -Math.PI / 2;
    road.receiveShadow = true;
    segment.add(road);

    // Grid embossing
    const gridLineMat = new THREE.MeshBasicMaterial({ color: 0xdeb887 });
    for (let g = 0; g < 10; g++) {
      const gLine = new THREE.Mesh(new THREE.PlaneGeometry(this.roadWidth, 0.08), gridLineMat);
      gLine.rotation.x = -Math.PI / 2;
      gLine.position.set(0, 0.005, -this.segmentLength / 2 + g * 4);
      segment.add(gLine);
    }

    // Lane dashes
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

    // Wafer Curbs
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

    // Roadside decorations
    const sideX = this.roadWidth / 2 + 2.2;
    for (let s = 0; s < 3; s++) {
      const zPos = -this.segmentLength / 2 + s * 13 + 6;

      const loliGroup = this.createLollipopTree();
      loliGroup.position.set(-sideX - (s % 2 === 0 ? 0.8 : 0.2), 0, zPos);
      segment.add(loliGroup);

      const cupcakeDeco = this.createRoadsideCupcake();
      cupcakeDeco.position.set(sideX + (s % 2 === 0 ? 0.3 : 0.9), 0, zPos);
      segment.add(cupcakeDeco);
    }

    return segment;
  }

  createLollipopTree(): THREE.Group {
    const group = new THREE.Group();

    const stickGeo = new THREE.CylinderGeometry(0.12, 0.12, 2.8, 10);
    const stickMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const stick = new THREE.Mesh(stickGeo, stickMat);
    stick.position.y = 1.4;
    stick.castShadow = true;
    group.add(stick);

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

  createRoadsideCupcake(): THREE.Group {
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

  initBakeryTown() {
    this.bakerySegments = [];
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

  createBakerySegment(): THREE.Group {
    const segment = new THREE.Group();
    const midX = 17;

    const leftShop = this.createBakeryShop(0xff8da1, 0xffffff);
    leftShop.position.set(-midX, 0, -15);
    leftShop.rotation.y = 0.2;
    segment.add(leftShop);

    const giantCake = this.createGiantCake();
    giantCake.position.set(midX + 2, 0, -20);
    giantCake.rotation.y = -0.25;
    segment.add(giantCake);

    const rightShop = this.createBakeryShop(0x06d6a0, 0xffd166);
    rightShop.position.set(midX + 1, 0, 15);
    rightShop.rotation.y = -0.15;
    segment.add(rightShop);

    return segment;
  }

  createBakeryShop(wallColor = 0xffa8ba, awningColor = 0xffffff): THREE.Group {
    const house = new THREE.Group();

    const wallGeo = new THREE.BoxGeometry(6.5, 4.5, 6);
    const wallMat = new THREE.MeshLambertMaterial({ color: wallColor });
    const walls = new THREE.Mesh(wallGeo, wallMat);
    walls.position.y = 2.25;
    house.add(walls);

    const roofGeo = new THREE.ConeGeometry(5.2, 2.4, 4);
    const roofMat = new THREE.MeshLambertMaterial({ color: 0x8b5a2b });
    const roof = new THREE.Mesh(roofGeo, roofMat);
    roof.position.y = 5.6;
    roof.rotation.y = Math.PI / 4;
    house.add(roof);

    const awningGeo = new THREE.BoxGeometry(4.2, 0.4, 1.8);
    const awningMat = new THREE.MeshLambertMaterial({ color: awningColor });
    const awning = new THREE.Mesh(awningGeo, awningMat);
    awning.position.set(0, 3.2, 3.5);
    awning.rotation.x = 0.25;
    house.add(awning);

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

  createGiantCake(): THREE.Group {
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

  initCloudsAndAtmosphere() {
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
  }

  initEntities() {
    this.player = new Player();
    this.scene.add(this.player.mesh);

    this.obstacleManager = new ObstacleManager(this.scene, (show) => {
      this.callbacks.onWarning(show);
    });

    this.collectibleManager = new CollectibleManager(this.scene, (item) => {
      this.handleCollectiblePickup(item);
    });
  }

  handleCollectiblePickup(item: Collectible) {
    this.score += item.points;
    this.treatsCount += 1;

    // Power-up activation & duration management
    if (item.isPowerUp && item.powerUpType) {
      if (item.powerUpType === 'magnet') {
        this.activePowerUps.set('magnet', { duration: 8.0, maxDuration: 8.0 });
        this.player.setMagnet(true);
      } else if (item.powerUpType === 'shield') {
        this.activePowerUps.set('shield', { duration: 10.0, maxDuration: 10.0 });
        this.player.setShield(true);
      }
    }

    this.callbacks.onPickup(item);
    this.emitMetrics();
  }

  emitMetrics() {
    const activePowerUpsList: ActivePowerUp[] = Array.from(this.activePowerUps.entries()).map(([type, data]) => ({
      type,
      duration: Math.max(0, data.duration),
      maxDuration: data.maxDuration
    }));

    this.callbacks.onMetricsUpdate({
      score: this.score,
      treats: this.treatsCount,
      distance: this.distance,
      speedMultiplier: this.currentSpeed / this.baseSpeed,
      activePowerUps: activePowerUpsList
    });
  }

  startGame() {
    this.isPlaying = true;
    this.isPaused = false;
    this.isGameOver = false;
    this.distance = 0;
    this.score = 0;
    this.treatsCount = 0;
    this.currentSpeed = this.baseSpeed;
    this.activePowerUps.clear();

    this.player.reset();
    this.obstacleManager.reset();
    this.collectibleManager.reset();
    this.callbacks.onWarning(false);
    this.emitMetrics();
  }

  restartGame() {
    this.startGame();
  }

  pauseGame() {
    if (this.isPlaying && !this.isGameOver) {
      this.isPaused = true;
    }
  }

  resumeGame() {
    if (this.isPlaying && !this.isGameOver) {
      this.isPaused = false;
      this.clock.getDelta(); // flush any accumulated delta while paused
    }
  }

  gameOver() {
    this.isPlaying = false;
    this.isPaused = false;
    this.isGameOver = true;
    this.callbacks.onWarning(false);
    soundEngine.playHit();
    this.callbacks.onGameOver();
  }

  updateEnvironment(speed: number, delta: number) {
    this.envTimer += delta;

    const trackMove = speed * delta;
    for (const segment of this.trackSegments) {
      segment.position.z += trackMove;
      if (segment.position.z > 25) {
        let minZ = 0;
        for (const s of this.trackSegments) {
          if (s.position.z < minZ) minZ = s.position.z;
        }
        segment.position.z = minZ - this.segmentLength;
      }
    }

    const bakeryMove = speed * this.bakerySpeedMultiplier * delta;
    for (const bSeg of this.bakerySegments) {
      bSeg.position.z += bakeryMove;
      if (bSeg.position.z > 25) {
        let minBZ = 0;
        for (const b of this.bakerySegments) {
          if (b.position.z < minBZ) minBZ = b.position.z;
        }
        bSeg.position.z = minBZ - this.bakerySegmentLength;
      }
    }

    for (const sign of this.swingingSigns) {
      sign.rotation.z = Math.sin(this.envTimer * 2.5) * 0.15;
    }

    for (const cloud of this.clouds) {
      cloud.position.x += delta * 1.2;
      cloud.position.z += speed * 0.08 * delta;
      if (cloud.position.x > 80) {
        cloud.position.x = -80;
      }
      if (cloud.position.z > 20) {
        cloud.position.z = -220;
      }
    }
  }

  animate() {
    this.animFrameId = requestAnimationFrame(this.animate);
    const delta = Math.min(this.clock.getDelta(), 0.1);

    if (this.isPaused) {
      this.renderer.render(this.scene, this.camera);
      return;
    }

    if (this.isPlaying) {
      // 1. Tick active power-ups countdowns
      for (const [type, data] of Array.from(this.activePowerUps.entries())) {
        data.duration -= delta;
        if (data.duration <= 0) {
          this.activePowerUps.delete(type);
          if (type === 'magnet') this.player.setMagnet(false);
          if (type === 'shield') this.player.setShield(false);
        }
      }

      const isMagnetActive = this.activePowerUps.has('magnet');

      this.currentSpeed = Math.min(this.maxSpeed, this.baseSpeed + (this.distance / 120) * 1.25);
      this.distance += this.currentSpeed * delta;
      this.score += delta * 12;
      this.emitMetrics();

      this.updateEnvironment(this.currentSpeed, delta);
      this.player.update(delta, true);

      this.obstacleManager.update(this.currentSpeed, delta, this.distance, this.player);
      
      // Check collision with shield protection
      if (this.obstacleManager.checkCollision(this.player, () => {
        // Shield absorbed obstacle: consume shield and notify
        this.activePowerUps.delete('shield');
        this.player.setShield(false);
        this.emitMetrics();
      })) {
        this.gameOver();
      }

      this.collectibleManager.update(this.currentSpeed, delta, this.distance, this.player, isMagnetActive);
      this.collectibleManager.checkCollisions(this.player);

      this.camera.position.x = THREE.MathUtils.lerp(
        this.camera.position.x,
        this.player.mesh.position.x * 0.4,
        delta * 6
      );
    } else {
      this.player.update(delta, false);
      this.updateEnvironment(3.5, delta);
    }

    this.renderer.render(this.scene, this.camera);
  }

  onWindowResize() {
    const width = this.canvas.parentElement ? this.canvas.parentElement.clientWidth : window.innerWidth;
    const height = this.canvas.parentElement ? this.canvas.parentElement.clientHeight : window.innerHeight;
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height);
  }

  moveLeft() {
    if (this.isPlaying && !this.isPaused) {
      this.player.moveLeft();
    }
  }

  moveRight() {
    if (this.isPlaying && !this.isPaused) {
      this.player.moveRight();
    }
  }

  jump() {
    if (this.isPlaying && !this.isPaused) {
      this.player.jump();
      soundEngine.playJump();
    }
  }

  slide() {
    if (this.isPlaying && !this.isPaused) {
      const triggered = this.player.slide();
      if (triggered) {
        soundEngine.playSlide();
      }
    }
  }

  cycleCharacter(): string {
    return this.player.cycleCharacterStyle();
  }

  setCharacterStyle(style: CharacterStyle) {
    this.player.setStyle(style);
  }

  dispose() {
    if (this.animFrameId !== null) {
      cancelAnimationFrame(this.animFrameId);
    }
    window.removeEventListener('resize', this.onWindowResize);
    this.renderer.dispose();
  }
}
