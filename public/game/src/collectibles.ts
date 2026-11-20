/**
 * Sweet Run - Collectible System & Power-Ups (src/collectibles.ts)
 */

import * as THREE from 'three';
import { soundEngine } from './audio';
import { Player } from './player';
import { PowerUpType } from './types';

export const COLLECTIBLE_TYPES = {
  CANDY: 'candy',
  STRAWBERRY: 'strawberry',
  CAKE_SLICE: 'cake_slice',
  GOLDEN_CAKE: 'golden_cake',
  MAGNET: 'magnet',
  SHIELD: 'shield'
} as const;

export type CollectibleType = typeof COLLECTIBLE_TYPES[keyof typeof COLLECTIBLE_TYPES];

export class Collectible {
  public type: CollectibleType;
  public lane: number;
  public baseY: number;
  public zPos: number;
  public laneWidth: number;
  public collected: boolean = false;
  public time: number;
  public points: number;
  public isGold: boolean;
  public isPowerUp: boolean = false;
  public powerUpType: PowerUpType | null = null;

  public mesh: THREE.Group;
  public boundingBox: THREE.Box3;
  public halo?: THREE.Mesh;
  public auraMesh?: THREE.Mesh;

  constructor(
    type: CollectibleType = COLLECTIBLE_TYPES.CANDY,
    lane: number = 0,
    yPos: number = 0.9,
    zPos: number = -50,
    laneWidth: number = 2.4
  ) {
    this.type = type;
    this.lane = lane;
    this.baseY = yPos;
    this.zPos = zPos;
    this.laneWidth = laneWidth;
    this.time = Math.random() * 5;

    switch (type) {
      case COLLECTIBLE_TYPES.MAGNET:
        this.points = 50;
        this.isGold = true;
        this.isPowerUp = true;
        this.powerUpType = 'magnet';
        break;
      case COLLECTIBLE_TYPES.SHIELD:
        this.points = 50;
        this.isGold = true;
        this.isPowerUp = true;
        this.powerUpType = 'shield';
        break;
      case COLLECTIBLE_TYPES.GOLDEN_CAKE:
        this.points = 100;
        this.isGold = true;
        break;
      case COLLECTIBLE_TYPES.CAKE_SLICE:
        this.points = 50;
        this.isGold = false;
        break;
      case COLLECTIBLE_TYPES.STRAWBERRY:
        this.points = 30;
        this.isGold = false;
        break;
      case COLLECTIBLE_TYPES.CANDY:
      default:
        this.points = 10;
        this.isGold = false;
        break;
    }

    this.mesh = new THREE.Group();
    this.boundingBox = new THREE.Box3();

    this.createModel();
    this.mesh.position.set(this.lane * this.laneWidth, this.baseY, this.zPos);
    this.updateBoundingBox();
  }

  createModel() {
    switch (this.type) {
      case COLLECTIBLE_TYPES.MAGNET:
        this.createMagnetModel();
        break;
      case COLLECTIBLE_TYPES.SHIELD:
        this.createShieldModel();
        break;
      case COLLECTIBLE_TYPES.GOLDEN_CAKE:
        this.createGoldenCakeModel();
        break;
      case COLLECTIBLE_TYPES.CAKE_SLICE:
        this.createCakeSliceModel();
        break;
      case COLLECTIBLE_TYPES.STRAWBERRY:
        this.createStrawberryModel();
        break;
      case COLLECTIBLE_TYPES.CANDY:
      default:
        this.createCandyCoinModel();
        break;
    }
  }

  /**
   * 🧲 Horseshoe Candy Magnet Power-up Model
   */
  createMagnetModel() {
    const magnetGroup = new THREE.Group();

    // Red candy U-shaped arch
    const archGeo = new THREE.TorusGeometry(0.45, 0.12, 10, 20, Math.PI);
    const archMat = new THREE.MeshStandardMaterial({
      color: 0xff0054,
      metalness: 0.2,
      roughness: 0.3,
      emissive: 0xff0054,
      emissiveIntensity: 0.2
    });
    const arch = new THREE.Mesh(archGeo, archMat);
    arch.rotation.z = Math.PI;
    arch.castShadow = true;
    magnetGroup.add(arch);

    // Silver metallic pole tips
    const poleGeo = new THREE.CylinderGeometry(0.12, 0.12, 0.28, 12);
    const poleMat = new THREE.MeshStandardMaterial({
      color: 0xffffff,
      metalness: 0.7,
      roughness: 0.2
    });

    const leftPole = new THREE.Mesh(poleGeo, poleMat);
    leftPole.position.set(-0.45, -0.14, 0);
    leftPole.castShadow = true;
    magnetGroup.add(leftPole);

    const rightPole = new THREE.Mesh(poleGeo, poleMat);
    rightPole.position.set(0.45, -0.14, 0);
    rightPole.castShadow = true;
    magnetGroup.add(rightPole);

    // Glowing magnetic aura ring
    const auraGeo = new THREE.TorusGeometry(0.7, 0.04, 8, 20);
    const auraMat = new THREE.MeshBasicMaterial({ color: 0xff4d6d });
    this.halo = new THREE.Mesh(auraGeo, auraMat);
    this.halo.rotation.x = Math.PI / 2;
    magnetGroup.add(this.halo);

    this.mesh.add(magnetGroup);
  }

  /**
   * 🛡️ Sugar Crystal Shield Power-up Model
   */
  createShieldModel() {
    const shieldGroup = new THREE.Group();

    // Central crystal shield emblem
    const crestGeo = new THREE.CylinderGeometry(0.55, 0.45, 0.15, 6);
    const crestMat = new THREE.MeshStandardMaterial({
      color: 0x48cae4,
      metalness: 0.3,
      roughness: 0.15,
      emissive: 0x0077b6,
      emissiveIntensity: 0.4
    });
    const crest = new THREE.Mesh(crestGeo, crestMat);
    crest.rotation.x = Math.PI / 2;
    crest.castShadow = true;
    shieldGroup.add(crest);

    // Star icon inside shield
    const starGeo = new THREE.OctahedronGeometry(0.25);
    const starMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
    const star = new THREE.Mesh(starGeo, starMat);
    shieldGroup.add(star);

    // Sparkling outer protective ring
    const haloGeo = new THREE.TorusGeometry(0.75, 0.045, 8, 24);
    const haloMat = new THREE.MeshBasicMaterial({ color: 0x90e0ef });
    this.halo = new THREE.Mesh(haloGeo, haloMat);
    this.halo.rotation.x = Math.PI / 2;
    shieldGroup.add(this.halo);

    this.mesh.add(shieldGroup);
  }

  createCandyCoinModel() {
    const geo = new THREE.CylinderGeometry(0.32, 0.32, 0.09, 16);
    const mat = new THREE.MeshLambertMaterial({ color: 0xffd166 });
    const coin = new THREE.Mesh(geo, mat);
    coin.rotation.x = Math.PI / 2;
    coin.castShadow = true;
    this.mesh.add(coin);

    const starGeo = new THREE.BoxGeometry(0.18, 0.18, 0.11);
    const starMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const star = new THREE.Mesh(starGeo, starMat);
    star.rotation.z = Math.PI / 4;
    this.mesh.add(star);
  }

  createStrawberryModel() {
    const berryGeo = new THREE.ConeGeometry(0.3, 0.55, 12);
    berryGeo.rotateX(Math.PI);
    const berryMat = new THREE.MeshLambertMaterial({ color: 0xff0054 });
    const berry = new THREE.Mesh(berryGeo, berryMat);
    berry.castShadow = true;
    this.mesh.add(berry);

    const leafGeo = new THREE.BoxGeometry(0.35, 0.05, 0.35);
    const leafMat = new THREE.MeshLambertMaterial({ color: 0x06d6a0 });
    const leaf = new THREE.Mesh(leafGeo, leafMat);
    leaf.position.y = 0.28;
    this.mesh.add(leaf);
  }

  createCakeSliceModel() {
    const cakeGroup = new THREE.Group();

    const cakeGeo = new THREE.CylinderGeometry(0.48, 0.48, 0.4, 3, 1, false, 0, Math.PI * 0.7);
    const spongeMat = new THREE.MeshLambertMaterial({ color: 0xffbe0b });
    const cake = new THREE.Mesh(cakeGeo, spongeMat);
    cake.castShadow = true;
    cakeGroup.add(cake);

    const creamGeo = new THREE.CylinderGeometry(0.49, 0.49, 0.1, 3, 1, false, 0, Math.PI * 0.7);
    const creamMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const cream = new THREE.Mesh(creamGeo, creamMat);
    cream.position.y = 0.22;
    cakeGroup.add(cream);

    const topBerry = new THREE.Mesh(
      new THREE.SphereGeometry(0.12, 8, 8),
      new THREE.MeshLambertMaterial({ color: 0xff0054 })
    );
    topBerry.position.set(0.1, 0.35, 0.1);
    cakeGroup.add(topBerry);

    this.mesh.add(cakeGroup);
  }

  createGoldenCakeModel() {
    const goldGroup = new THREE.Group();

    const cakeGeo = new THREE.CylinderGeometry(0.55, 0.55, 0.48, 3, 1, false, 0, Math.PI * 0.75);
    const goldMat = new THREE.MeshStandardMaterial({
      color: 0xffd700,
      metalness: 0.65,
      roughness: 0.25,
      emissive: 0xffaa00,
      emissiveIntensity: 0.3
    });
    const cake = new THREE.Mesh(cakeGeo, goldMat);
    cake.castShadow = true;
    goldGroup.add(cake);

    const haloGeo = new THREE.TorusGeometry(0.65, 0.04, 8, 20);
    const haloMat = new THREE.MeshBasicMaterial({ color: 0xfff07c });
    this.halo = new THREE.Mesh(haloGeo, haloMat);
    this.halo.rotation.x = Math.PI / 2;
    goldGroup.add(this.halo);

    this.mesh.add(goldGroup);
  }

  updateBoundingBox() {
    this.mesh.updateMatrixWorld(true);
    this.boundingBox.setFromObject(this.mesh);
  }

  update(speed: number, delta: number) {
    this.time += delta;
    this.mesh.position.z += speed * delta;

    this.mesh.rotation.y += delta * (this.isPowerUp ? 3.5 : 2.8);
    this.mesh.position.y = this.baseY + Math.sin(this.time * 4) * 0.12;

    if (this.halo) {
      this.halo.rotation.z += delta * 2;
    }

    if (this.collected) {
      this.mesh.scale.multiplyScalar(0.85);
      this.mesh.position.y += delta * 4;
    }

    this.updateBoundingBox();
  }
}

export class CollectibleManager {
  public scene: THREE.Scene;
  public onCollect?: (item: Collectible) => void;
  public collectibles: Collectible[] = [];
  public spawnDistance: number = -80;
  public despawnZ: number = 16;
  public laneWidth: number = 2.4;
  public patternCooldown: number = 0;
  public powerupCooldown: number = 8.0; // Time until next power-up can spawn

  constructor(scene: THREE.Scene, onCollectCallback?: (item: Collectible) => void) {
    this.scene = scene;
    this.onCollect = onCollectCallback;
  }

  spawnPowerUp(distance: number = 0) {
    const lanes = [-1, 0, 1];
    const lane = lanes[Math.floor(Math.random() * lanes.length)];
    const type: CollectibleType = Math.random() < 0.5 ? COLLECTIBLE_TYPES.MAGNET : COLLECTIBLE_TYPES.SHIELD;

    const powerUp = new Collectible(type, lane, 1.1, this.spawnDistance - 5, this.laneWidth);
    this.scene.add(powerUp.mesh);
    this.collectibles.push(powerUp);

    // Schedule next power-up in ~16 - 22 seconds
    this.powerupCooldown = 16 + Math.random() * 6;
  }

  spawnPattern(distance: number = 0) {
    const lanes = [-1, 0, 1];
    const chosenLane = lanes[Math.floor(Math.random() * lanes.length)];
    const roll = Math.random();

    if (roll < 0.45) {
      const type = Math.random() < 0.6 ? COLLECTIBLE_TYPES.CANDY : COLLECTIBLE_TYPES.CAKE_SLICE;
      for (let i = 0; i < 4; i++) {
        const item = new Collectible(type, chosenLane, 0.85, this.spawnDistance - (i * 3.5), this.laneWidth);
        this.scene.add(item.mesh);
        this.collectibles.push(item);
      }
    } else if (roll < 0.75) {
      const heights = [0.85, 1.8, 2.3, 1.8, 0.85];
      const type = (distance > 200 && Math.random() < 0.3) ? COLLECTIBLE_TYPES.GOLDEN_CAKE : COLLECTIBLE_TYPES.STRAWBERRY;
      for (let i = 0; i < heights.length; i++) {
        const item = new Collectible(type, chosenLane, heights[i], this.spawnDistance - (i * 3.2), this.laneWidth);
        this.scene.add(item.mesh);
        this.collectibles.push(item);
      }
    } else {
      const patternLanes = Math.random() < 0.5 ? [-1, 0, 1] : [1, 0, -1];
      for (let i = 0; i < patternLanes.length; i++) {
        const item = new Collectible(COLLECTIBLE_TYPES.CAKE_SLICE, patternLanes[i], 0.85, this.spawnDistance - (i * 5.0), this.laneWidth);
        this.scene.add(item.mesh);
        this.collectibles.push(item);
      }
    }
  }

  update(speed: number, delta: number, distance: number = 0, player?: Player, isMagnetActive: boolean = false) {
    const playerPos = player ? player.mesh.position : null;

    for (let i = this.collectibles.length - 1; i >= 0; i--) {
      const item = this.collectibles[i];
      item.update(speed, delta);

      // Magnet pull physics: attract nearby non-collected treats directly toward player!
      if (isMagnetActive && playerPos && !item.collected && !item.isPowerUp) {
        const distZ = item.mesh.position.z - playerPos.z;
        const distX = item.mesh.position.x - playerPos.x;
        const dist3D = Math.sqrt(distX * distX + distZ * distZ);

        // Within 16 units of player ahead or alongside
        if (dist3D < 16 && distZ < 2) {
          // Accelerate toward player
          const pullSpeed = 22 * delta;
          item.mesh.position.x = THREE.MathUtils.lerp(item.mesh.position.x, playerPos.x, Math.min(pullSpeed * 1.5, 0.4));
          item.mesh.position.y = THREE.MathUtils.lerp(item.mesh.position.y, playerPos.y + 0.8, Math.min(pullSpeed, 0.3));
          item.mesh.position.z = THREE.MathUtils.lerp(item.mesh.position.z, playerPos.z, Math.min(pullSpeed, 0.4));

          // If reached player, collect immediately
          if (dist3D < 1.2) {
            item.collected = true;
            if (this.onCollect) {
              this.onCollect(item);
            }
            soundEngine.playCollect(item.isGold);
          }
        }
      }

      if (item.mesh.position.z > this.despawnZ || (item.collected && item.mesh.scale.x < 0.05)) {
        this.scene.remove(item.mesh);
        this.collectibles.splice(i, 1);
      }
    }

    // Pattern cooldown
    this.patternCooldown -= delta;
    if (this.patternCooldown <= 0) {
      this.spawnPattern(distance);
      this.patternCooldown = 4.2;
    }

    // Power-up cooldown
    this.powerupCooldown -= delta;
    if (this.powerupCooldown <= 0) {
      this.spawnPowerUp(distance);
    }
  }

  checkCollisions(player: Player) {
    for (const item of this.collectibles) {
      if (!item.collected && Math.abs(item.mesh.position.z - player.mesh.position.z) < 2.0) {
        if (item.boundingBox.intersectsBox(player.boundingBox)) {
          item.collected = true;
          if (this.onCollect) {
            this.onCollect(item);
          }

          if (item.isPowerUp) {
            soundEngine.playPowerUpCollect(item.powerUpType === 'shield');
          } else {
            soundEngine.playCollect(item.isGold);
          }
        }
      }
    }
  }

  reset() {
    for (const item of this.collectibles) {
      this.scene.remove(item.mesh);
    }
    this.collectibles = [];
    this.patternCooldown = 2.0;
    this.powerupCooldown = 6.0;
  }
}
