/**
 * Sweet Run - Collectible System (js/collectible.js)
 * 
 * Features:
 * 1. Collectible Types:
 *    - Candy Coin (+10 pts)
 *    - Sweet Strawberry (+30 pts)
 *    - Cake Slice (+50 pts)
 *    - Golden Cake (+100 bonus pts, shiny golden shimmer)
 * 2. Meaningful Spawn Patterns:
 *    - Straight lines along a lane
 *    - Jump arcs over obstacles
 *    - Zigzag patterns encouraging lane switches
 * 3. Particle/audio/floating score pickup feedback
 */

const COLLECTIBLE_TYPES = {
  CANDY: 'candy',
  STRAWBERRY: 'strawberry',
  CAKE_SLICE: 'cake_slice',
  GOLDEN_CAKE: 'golden_cake'
};

class Collectible {
  constructor(type = COLLECTIBLE_TYPES.CANDY, lane = 0, yPos = 0.9, zPos = -50, laneWidth = 2.4) {
    this.type = type;
    this.lane = lane;
    this.baseY = yPos;
    this.zPos = zPos;
    this.laneWidth = laneWidth;
    this.collected = false;
    this.time = Math.random() * 5;

    // Point values
    switch (type) {
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
   * 1. Candy Coin
   */
  createCandyCoinModel() {
    const geo = new THREE.CylinderGeometry(0.32, 0.32, 0.09, 16);
    const mat = new THREE.MeshLambertMaterial({ color: 0xffd166 }); // Warm golden yellow
    const coin = new THREE.Mesh(geo, mat);
    coin.rotation.x = Math.PI / 2;
    coin.castShadow = true;
    this.mesh.add(coin);

    // Star icon inside coin
    const starGeo = new THREE.BoxGeometry(0.18, 0.18, 0.11);
    const starMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
    const star = new THREE.Mesh(starGeo, starMat);
    star.rotation.z = Math.PI / 4;
    this.mesh.add(star);
  }

  /**
   * 2. Sweet Strawberry
   */
  createStrawberryModel() {
    // Cone berry
    const berryGeo = new THREE.ConeGeometry(0.3, 0.55, 12);
    berryGeo.rotateX(Math.PI);
    const berryMat = new THREE.MeshLambertMaterial({ color: 0xff0054 }); // Bright red
    const berry = new THREE.Mesh(berryGeo, berryMat);
    berry.castShadow = true;
    this.mesh.add(berry);

    // Green leafy stem
    const leafGeo = new THREE.BoxGeometry(0.35, 0.05, 0.35);
    const leafMat = new THREE.MeshLambertMaterial({ color: 0x06d6a0 }); // Fresh mint green
    const leaf = new THREE.Mesh(leafGeo, leafMat);
    leaf.position.y = 0.28;
    this.mesh.add(leaf);
  }

  /**
   * 3. Delicious Cake Slice
   */
  createCakeSliceModel() {
    const cakeGroup = new THREE.Group();

    // Cake wedge (cylinder slice)
    const cakeGeo = new THREE.CylinderGeometry(0.48, 0.48, 0.4, 3, 1, false, 0, Math.PI * 0.7);
    const spongeMat = new THREE.MeshLambertMaterial({ color: 0xffbe0b }); // Honey sponge
    const cake = new THREE.Mesh(cakeGeo, spongeMat);
    cake.castShadow = true;
    cakeGroup.add(cake);

    // Cream layer on top
    const creamGeo = new THREE.CylinderGeometry(0.49, 0.49, 0.1, 3, 1, false, 0, Math.PI * 0.7);
    const creamMat = new THREE.MeshLambertMaterial({ color: 0xffffff }); // Vanilla whipped cream
    const cream = new THREE.Mesh(creamGeo, creamMat);
    cream.position.y = 0.22;
    cakeGroup.add(cream);

    // Strawberry on top of cake
    const topBerry = new THREE.Mesh(new THREE.SphereGeometry(0.12, 8, 8), new THREE.MeshLambertMaterial({ color: 0xff0054 }));
    topBerry.position.set(0.1, 0.35, 0.1);
    cakeGroup.add(topBerry);

    this.mesh.add(cakeGroup);
  }

  /**
   * 4. Radiant Golden Cake Piece
   */
  createGoldenCakeModel() {
    const goldGroup = new THREE.Group();

    // Golden cake wedge
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

    // Sparkling golden aura ring
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

  update(speed, delta) {
    this.time += delta;
    this.mesh.position.z += speed * delta;

    // Gentle floating bob & spin
    this.mesh.rotation.y += delta * 2.8;
    this.mesh.position.y = this.baseY + Math.sin(this.time * 4) * 0.12;

    if (this.halo) {
      this.halo.rotation.z += delta * 2;
    }

    // Collection shrink animation
    if (this.collected) {
      this.mesh.scale.multiplyScalar(0.85);
      this.mesh.position.y += delta * 4;
    }

    this.updateBoundingBox();
  }
}

/**
 * Manages spawning patterns and collision checking for treats
 */
class CollectibleManager {
  /**
   * @param {THREE.Scene} scene
   * @param {function} onCollectCallback - Called when player picks up an item
   */
  constructor(scene, onCollectCallback = null) {
    this.scene = scene;
    this.onCollect = onCollectCallback;
    this.collectibles = [];
    this.spawnDistance = -80;
    this.despawnZ = 16;
    this.laneWidth = 2.4;
    this.patternCooldown = 0;
  }

  /**
   * Spawns a structured pattern of treats
   */
  spawnPattern(distance = 0) {
    const lanes = [-1, 0, 1];
    const chosenLane = lanes[Math.floor(Math.random() * lanes.length)];
    const roll = Math.random();

    // 1. Straight line along lane (3-4 items)
    if (roll < 0.45) {
      const type = Math.random() < 0.6 ? COLLECTIBLE_TYPES.CANDY : COLLECTIBLE_TYPES.CAKE_SLICE;
      for (let i = 0; i < 4; i++) {
        const item = new Collectible(type, chosenLane, 0.85, this.spawnDistance - (i * 3.5), this.laneWidth);
        this.scene.add(item.mesh);
        this.collectibles.push(item);
      }
    }
    // 2. Jump arc pattern (over hurdles!)
    else if (roll < 0.75) {
      const heights = [0.85, 1.8, 2.3, 1.8, 0.85];
      const type = (distance > 200 && Math.random() < 0.3) ? COLLECTIBLE_TYPES.GOLDEN_CAKE : COLLECTIBLE_TYPES.STRAWBERRY;
      for (let i = 0; i < heights.length; i++) {
        const item = new Collectible(type, chosenLane, heights[i], this.spawnDistance - (i * 3.2), this.laneWidth);
        this.scene.add(item.mesh);
        this.collectibles.push(item);
      }
    }
    // 3. Zigzag pattern between lanes
    else {
      const patternLanes = Math.random() < 0.5 ? [-1, 0, 1] : [1, 0, -1];
      for (let i = 0; i < patternLanes.length; i++) {
        const item = new Collectible(COLLECTIBLE_TYPES.CAKE_SLICE, patternLanes[i], 0.85, this.spawnDistance - (i * 5.0), this.laneWidth);
        this.scene.add(item.mesh);
        this.collectibles.push(item);
      }
    }
  }

  update(speed, delta, distance = 0) {
    // 1. Update existing items
    for (let i = this.collectibles.length - 1; i >= 0; i--) {
      const item = this.collectibles[i];
      item.update(speed, delta);

      // Despawn if passed or fully shrunk
      if (item.mesh.position.z > this.despawnZ || (item.collected && item.mesh.scale.x < 0.05)) {
        this.scene.remove(item.mesh);
        this.collectibles.splice(i, 1);
      }
    }

    // 2. Periodically spawn patterns
    this.patternCooldown -= delta;
    if (this.patternCooldown <= 0) {
      this.spawnPattern(distance);
      this.patternCooldown = 4.2; // Spawn pattern every ~4.2 seconds
    }
  }

  /**
   * Check collision with player to pick up treats
   */
  checkCollisions(player) {
    for (let item of this.collectibles) {
      if (!item.collected && Math.abs(item.mesh.position.z - player.mesh.position.z) < 2.0) {
        if (item.boundingBox.intersectsBox(player.boundingBox)) {
          item.collected = true;
          if (this.onCollect) {
            this.onCollect(item);
          }
          if (window.soundEngine) {
            window.soundEngine.playCollect(item.isGold);
          }
        }
      }
    }
  }

  reset() {
    for (let item of this.collectibles) {
      this.scene.remove(item.mesh);
    }
    this.collectibles = [];
    this.patternCooldown = 2.0;
  }
}
