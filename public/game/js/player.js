/**
 * Sweet Run - Player Controller (js/player.js)
 * 
 * Features:
 * - Fully articulated 3D cartoon characters with real multi-joint running animation:
 *   * Sweets Runner Girl (Chibi sweets baker with animated twintails, dress, arms, knees, candy sneakers)
 *   * Blue Hoodie Runner (Athletic boy runner with hoodie, shorts, and articulated limbs)
 *   * Optional 3D Meshy AI model inspection
 * - Dynamic running gait:
 *   * Real hip-joint leg swing with knee flexion on backswing
 *   * Arm pumping counter-balancing leg strides with bent elbows
 *   * Vertical running bounce synced with footstep frequency
 *   * Torso forward sprint lean and pelvic twist
 *   * Dynamic hair/twintail secondary physics
 *   * Lane-change banking
 *   * Dynamic jump pose (tucked knees, reaching arms) and landing squash
 * - Multi-lane navigation (-1 = Left, 0 = Center, 1 = Right)
 * - Jump physics with gravity
 */

class Player {
  constructor() {
    // Lane configuration: -1 = Left, 0 = Center, 1 = Right
    this.currentLane = 0;
    this.laneWidth = 2.4;
    this.targetX = 0;
    
    // Jump & gravity physics
    this.groundY = 0.0;
    this.velocityY = 0;
    this.gravity = -38;
    this.jumpForce = 13.5;
    this.isJumping = false;
    this.landingSquash = 0;
    
    // Animation timers
    this.runTimer = 0;
    this.idleTimer = 0;

    // Character styles: 'sweets' (Default), 'hoodie', 'meshy'
    this.currentStyle = 'sweets';
    this.hasCustomModel = false;
    this.customModel = null;

    // Root 3D Object
    this.mesh = new THREE.Group();
    this.mesh.position.set(0, this.groundY, 0);

    // Container for character model geometry & transforms
    this.characterContainer = new THREE.Group();
    this.mesh.add(this.characterContainer);

    // Collision box helper
    this.boundingBox = new THREE.Box3();
    
    // Build default animated cartoon character
    this.buildSweetsModel();

    // Asynchronously load Meshy AI 3D model for fallback / inspection
    this.loadMeshyCharacter();
  }

  /**
   * Set character style ('sweets', 'hoodie', or 'meshy')
   */
  setStyle(styleName) {
    if (styleName === this.currentStyle && this.modelRoot) return;
    this.currentStyle = styleName;

    // Clear previous children
    while (this.characterContainer.children.length > 0) {
      this.characterContainer.remove(this.characterContainer.children[0]);
    }

    if (styleName === 'hoodie') {
      this.buildHoodieModel();
    } else if (styleName === 'meshy' && this.customModel) {
      this.characterContainer.add(this.customModel);
    } else {
      this.currentStyle = 'sweets';
      this.buildSweetsModel();
    }

    this.updateBoundingBox();
  }

  /**
   * Cycle to the next character style
   */
  cycleCharacterStyle() {
    if (this.currentStyle === 'sweets') {
      this.setStyle('hoodie');
    } else if (this.currentStyle === 'hoodie' && this.hasCustomModel) {
      this.setStyle('meshy');
    } else {
      this.setStyle('sweets');
    }
    return this.getStyleDisplayName();
  }

  getStyleDisplayName() {
    switch (this.currentStyle) {
      case 'hoodie': return 'Runner: 🏃 Blue Hoodie';
      case 'meshy': return 'Runner: 🗿 3D Statue';
      case 'sweets':
      default: return 'Runner: 🍬 Sweets Girl';
    }
  }

  /**
   * Builds the Chibi Sweets Runner Girl:
   * Strawberry twintails, baker dress with cupcake skirt, chef cap,
   * articulated shoulders & elbows, hips, knees, and candy sneakers.
   */
  buildSweetsModel() {
    this.modelRoot = new THREE.Group();
    this.characterContainer.add(this.modelRoot);

    const skinMat = new THREE.MeshLambertMaterial({ color: 0xffdfd3 }); // Peach skin
    const hairMat = new THREE.MeshLambertMaterial({ color: 0xff6b9d }); // Strawberry pink
    const ribbonMat = new THREE.MeshLambertMaterial({ color: 0xff1493 }); // Deep pink
    const dressTopMat = new THREE.MeshLambertMaterial({ color: 0xffffff }); // White blouse
    const skirtMat = new THREE.MeshLambertMaterial({ color: 0xff4d88 }); // Strawberry pink skirt
    const trimMat = new THREE.MeshBasicMaterial({ color: 0xffffff }); // White icing trim
    const sockMat = new THREE.MeshLambertMaterial({ color: 0xffffff }); // White socks
    const shoeMat = new THREE.MeshLambertMaterial({ color: 0xff2a6d }); // Sweet candy sneakers
    const eyeMat = new THREE.MeshBasicMaterial({ color: 0x241722 }); // Dark cocoa eyes
    const glintMat = new THREE.MeshBasicMaterial({ color: 0xffffff }); // Eye highlight
    const cheekMat = new THREE.MeshBasicMaterial({ color: 0xff8fa3, side: THREE.DoubleSide });

    // 1. Upper Body Group (Torso, Head, Arms) - tilts & bobs while running
    this.bodyGroup = new THREE.Group();
    this.bodyGroup.position.set(0, 0, 0);
    this.modelRoot.add(this.bodyGroup);

    // Torso / Blouse
    const torsoGeo = new THREE.CylinderGeometry(0.22, 0.26, 0.35, 14);
    const torso = new THREE.Mesh(torsoGeo, dressTopMat);
    torso.position.y = 0.68;
    torso.castShadow = true;
    this.bodyGroup.add(torso);

    // Blouse collar bow
    const chestBow = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.08, 0.06), ribbonMat);
    chestBow.position.set(0, 0.76, -0.25);
    this.bodyGroup.add(chestBow);

    // Flared Cupcake Skirt
    const skirtGeo = new THREE.ConeGeometry(0.44, 0.30, 16, 1, true);
    this.skirt = new THREE.Mesh(skirtGeo, skirtMat);
    this.skirt.position.y = 0.44;
    this.skirt.castShadow = true;
    this.bodyGroup.add(this.skirt);

    // Skirt icing ruffle border
    const trimGeo = new THREE.TorusGeometry(0.42, 0.035, 8, 20);
    const trim = new THREE.Mesh(trimGeo, trimMat);
    trim.rotation.x = Math.PI / 2;
    trim.position.y = 0.30;
    this.bodyGroup.add(trim);

    // Head
    const headGeo = new THREE.SphereGeometry(0.36, 18, 18);
    const head = new THREE.Mesh(headGeo, skinMat);
    head.position.y = 1.05;
    head.castShadow = true;
    this.bodyGroup.add(head);

    // Hair base cap
    const hairCapGeo = new THREE.SphereGeometry(0.38, 16, 16, 0, Math.PI * 2, 0, Math.PI * 0.6);
    const hairCap = new THREE.Mesh(hairCapGeo, hairMat);
    hairCap.position.y = 1.08;
    this.bodyGroup.add(hairCap);

    // Front bangs
    const bangGeo = new THREE.BoxGeometry(0.14, 0.16, 0.1);
    for (let b = -2; b <= 2; b++) {
      const bang = new THREE.Mesh(bangGeo, hairMat);
      bang.position.set(b * 0.09, 1.24 - Math.abs(b) * 0.03, -0.32);
      bang.rotation.x = 0.2;
      this.bodyGroup.add(bang);
    }

    // Left Twintail
    this.leftTwintail = new THREE.Group();
    const pigtailGeo = new THREE.ConeGeometry(0.12, 0.46, 12);
    pigtailGeo.rotateX(Math.PI);
    const leftPigtailMesh = new THREE.Mesh(pigtailGeo, hairMat);
    leftPigtailMesh.position.y = -0.23;
    this.leftTwintail.add(leftPigtailMesh);
    this.leftTwintail.position.set(-0.38, 1.15, 0.05);
    this.leftTwintail.rotation.z = 0.42;
    this.bodyGroup.add(this.leftTwintail);

    // Right Twintail
    this.rightTwintail = new THREE.Group();
    const rightPigtailMesh = new THREE.Mesh(pigtailGeo, hairMat);
    rightPigtailMesh.position.y = -0.23;
    this.rightTwintail.add(rightPigtailMesh);
    this.rightTwintail.position.set(0.38, 1.15, 0.05);
    this.rightTwintail.rotation.z = -0.42;
    this.bodyGroup.add(this.rightTwintail);

    // Ribbons
    const bowGeo = new THREE.SphereGeometry(0.08, 8, 8);
    const leftBow = new THREE.Mesh(bowGeo, ribbonMat);
    leftBow.position.set(-0.35, 1.18, 0.05);
    this.bodyGroup.add(leftBow);

    const rightBow = new THREE.Mesh(bowGeo, ribbonMat);
    rightBow.position.set(0.35, 1.18, 0.05);
    this.bodyGroup.add(rightBow);

    // Anime Eyes (facing -Z down the road)
    const eyeGeo = new THREE.SphereGeometry(0.07, 12, 12);
    const leftEye = new THREE.Mesh(eyeGeo, eyeMat);
    leftEye.position.set(-0.14, 1.05, -0.32);
    this.bodyGroup.add(leftEye);

    const rightEye = new THREE.Mesh(eyeGeo, eyeMat);
    rightEye.position.set(0.14, 1.05, -0.32);
    this.bodyGroup.add(rightEye);

    // Eye sparkles
    const glintGeo = new THREE.SphereGeometry(0.026, 8, 8);
    const leftGlint = new THREE.Mesh(glintGeo, glintMat);
    leftGlint.position.set(-0.12, 1.07, -0.37);
    this.bodyGroup.add(leftGlint);

    const rightGlint = new THREE.Mesh(glintGeo, glintMat);
    rightGlint.position.set(0.16, 1.07, -0.37);
    this.bodyGroup.add(rightGlint);

    // Rosy Cheeks
    const cheekGeo = new THREE.CircleGeometry(0.065, 12);
    const leftCheek = new THREE.Mesh(cheekGeo, cheekMat);
    leftCheek.position.set(-0.24, 0.96, -0.32);
    leftCheek.rotation.y = Math.PI;
    this.bodyGroup.add(leftCheek);

    const rightCheek = new THREE.Mesh(cheekGeo, cheekMat);
    rightCheek.position.set(0.24, 0.96, -0.32);
    rightCheek.rotation.y = Math.PI;
    this.bodyGroup.add(rightCheek);

    // Pastry Chef Cap
    const capGeo = new THREE.ConeGeometry(0.18, 0.22, 12);
    const cap = new THREE.Mesh(capGeo, dressTopMat);
    cap.position.set(0.12, 1.48, 0);
    cap.rotation.z = -0.2;
    this.bodyGroup.add(cap);

    const capPuff = new THREE.Mesh(new THREE.SphereGeometry(0.06, 8, 8), ribbonMat);
    capPuff.position.set(0.14, 1.58, 0);
    this.bodyGroup.add(capPuff);

    // Articulated Arms (Shoulders + Bent Elbows)
    const upperArmGeo = new THREE.CylinderGeometry(0.06, 0.055, 0.20, 8);
    const forearmGeo = new THREE.CylinderGeometry(0.055, 0.05, 0.18, 8);
    const handGeo = new THREE.SphereGeometry(0.065, 8, 8);

    // Left Arm Hierarchy
    this.leftShoulder = new THREE.Group();
    this.leftShoulder.position.set(-0.30, 0.76, 0);
    const leftUpperArm = new THREE.Mesh(upperArmGeo, skinMat);
    leftUpperArm.position.y = -0.10;
    leftUpperArm.castShadow = true;
    this.leftShoulder.add(leftUpperArm);

    this.leftElbow = new THREE.Group();
    this.leftElbow.position.set(0, -0.20, 0);
    const leftForearm = new THREE.Mesh(forearmGeo, skinMat);
    leftForearm.position.y = -0.09;
    leftForearm.castShadow = true;
    this.leftElbow.add(leftForearm);

    const leftHand = new THREE.Mesh(handGeo, skinMat);
    leftHand.position.y = -0.18;
    this.leftElbow.add(leftHand);

    this.leftShoulder.add(this.leftElbow);
    this.bodyGroup.add(this.leftShoulder);

    // Right Arm Hierarchy
    this.rightShoulder = new THREE.Group();
    this.rightShoulder.position.set(0.30, 0.76, 0);
    const rightUpperArm = new THREE.Mesh(upperArmGeo, skinMat);
    rightUpperArm.position.y = -0.10;
    rightUpperArm.castShadow = true;
    this.rightShoulder.add(rightUpperArm);

    this.rightElbow = new THREE.Group();
    this.rightElbow.position.set(0, -0.20, 0);
    const rightForearm = new THREE.Mesh(forearmGeo, skinMat);
    rightForearm.position.y = -0.09;
    rightForearm.castShadow = true;
    this.rightElbow.add(rightForearm);

    const rightHand = new THREE.Mesh(handGeo, skinMat);
    rightHand.position.y = -0.18;
    this.rightElbow.add(rightHand);

    this.rightShoulder.add(this.rightElbow);
    this.bodyGroup.add(this.rightShoulder);

    // 2. Hip & Leg Hierarchy (attached at hip level y=0.38)
    this.hipGroup = new THREE.Group();
    this.hipGroup.position.set(0, 0.38, 0);
    this.modelRoot.add(this.hipGroup);

    const thighGeo = new THREE.CylinderGeometry(0.07, 0.065, 0.18, 8);
    const shinGeo = new THREE.CylinderGeometry(0.065, 0.06, 0.14, 8);
    const shoeBodyGeo = new THREE.BoxGeometry(0.16, 0.12, 0.25);
    const shoeSoleGeo = new THREE.BoxGeometry(0.17, 0.03, 0.27);
    const shoeToeGeo = new THREE.BoxGeometry(0.15, 0.07, 0.09);

    // Left Leg Hierarchy
    this.leftHip = new THREE.Group();
    this.leftHip.position.set(-0.15, 0, 0);
    const leftThigh = new THREE.Mesh(thighGeo, skinMat);
    leftThigh.position.y = -0.09;
    leftThigh.castShadow = true;
    this.leftHip.add(leftThigh);

    this.leftKnee = new THREE.Group();
    this.leftKnee.position.set(0, -0.18, 0);
    const leftShin = new THREE.Mesh(shinGeo, sockMat);
    leftShin.position.y = -0.07;
    leftShin.castShadow = true;
    this.leftKnee.add(leftShin);

    this.leftFoot = new THREE.Group();
    this.leftFoot.position.set(0, -0.14, 0);
    
    const leftShoeBody = new THREE.Mesh(shoeBodyGeo, shoeMat);
    leftShoeBody.position.set(0, 0.06, -0.04);
    leftShoeBody.castShadow = true;
    this.leftFoot.add(leftShoeBody);

    const leftShoeSole = new THREE.Mesh(shoeSoleGeo, trimMat);
    leftShoeSole.position.set(0, 0.015, -0.04);
    this.leftFoot.add(leftShoeSole);

    const leftShoeToe = new THREE.Mesh(shoeToeGeo, trimMat);
    leftShoeToe.position.set(0, 0.04, -0.14);
    this.leftFoot.add(leftShoeToe);

    this.leftKnee.add(this.leftFoot);
    this.leftHip.add(this.leftKnee);
    this.hipGroup.add(this.leftHip);

    // Right Leg Hierarchy
    this.rightHip = new THREE.Group();
    this.rightHip.position.set(0.15, 0, 0);
    const rightThigh = new THREE.Mesh(thighGeo, skinMat);
    rightThigh.position.y = -0.09;
    rightThigh.castShadow = true;
    this.rightHip.add(rightThigh);

    this.rightKnee = new THREE.Group();
    this.rightKnee.position.set(0, -0.18, 0);
    const rightShin = new THREE.Mesh(shinGeo, sockMat);
    rightShin.position.y = -0.07;
    rightShin.castShadow = true;
    this.rightKnee.add(rightShin);

    this.rightFoot = new THREE.Group();
    this.rightFoot.position.set(0, -0.14, 0);

    const rightShoeBody = new THREE.Mesh(shoeBodyGeo, shoeMat);
    rightShoeBody.position.set(0, 0.06, -0.04);
    rightShoeBody.castShadow = true;
    this.rightFoot.add(rightShoeBody);

    const rightShoeSole = new THREE.Mesh(shoeSoleGeo, trimMat);
    rightShoeSole.position.set(0, 0.015, -0.04);
    this.rightFoot.add(rightShoeSole);

    const rightShoeToe = new THREE.Mesh(shoeToeGeo, trimMat);
    rightShoeToe.position.set(0, 0.04, -0.14);
    this.rightFoot.add(rightShoeToe);

    this.rightKnee.add(this.rightFoot);
    this.rightHip.add(this.rightKnee);
    this.hipGroup.add(this.rightHip);
  }

  /**
   * Builds the Blue Hoodie Runner:
   * Cool sporty cartoon boy runner with vibrant blue hoodie,
   * drawstring cords, dark shorts, white crew socks, and running sneakers.
   * Fully rigged with the identical multi-joint running kinematics!
   */
  buildHoodieModel() {
    this.modelRoot = new THREE.Group();
    this.characterContainer.add(this.modelRoot);

    const skinMat = new THREE.MeshLambertMaterial({ color: 0xffdfd3 }); // Peach skin
    const hairMat = new THREE.MeshLambertMaterial({ color: 0x6f4e37 }); // Warm brunette hair
    const hoodieMat = new THREE.MeshLambertMaterial({ color: 0x1e88e5 }); // Royal blue hoodie
    const darkHoodieMat = new THREE.MeshLambertMaterial({ color: 0x1565c0 }); // Darker blue pocket/trim
    const whiteMat = new THREE.MeshLambertMaterial({ color: 0xffffff }); // White accents
    const shortsMat = new THREE.MeshLambertMaterial({ color: 0x1a237e }); // Navy blue shorts
    const sneakerMat = new THREE.MeshLambertMaterial({ color: 0x0288d1 }); // Blue sneakers
    const eyeMat = new THREE.MeshBasicMaterial({ color: 0x1d3557 }); // Deep navy anime eyes
    const glintMat = new THREE.MeshBasicMaterial({ color: 0xffffff });

    // 1. Upper Body Group (Torso, Head, Arms)
    this.bodyGroup = new THREE.Group();
    this.modelRoot.add(this.bodyGroup);

    // Blue Hoodie Torso
    const hoodieGeo = new THREE.BoxGeometry(0.50, 0.44, 0.34);
    const hoodie = new THREE.Mesh(hoodieGeo, hoodieMat);
    hoodie.position.y = 0.64;
    hoodie.castShadow = true;
    this.bodyGroup.add(hoodie);

    // Front kangaroo pouch pocket
    const pocketGeo = new THREE.BoxGeometry(0.36, 0.16, 0.06);
    const pocket = new THREE.Mesh(pocketGeo, darkHoodieMat);
    pocket.position.set(0, 0.54, -0.16);
    this.bodyGroup.add(pocket);

    // White drawstring cords
    const cordGeo = new THREE.CylinderGeometry(0.015, 0.015, 0.18, 6);
    const leftCord = new THREE.Mesh(cordGeo, whiteMat);
    leftCord.position.set(-0.06, 0.70, -0.18);
    this.bodyGroup.add(leftCord);

    const rightCord = new THREE.Mesh(cordGeo, whiteMat);
    rightCord.position.set(0.06, 0.70, -0.18);
    this.bodyGroup.add(rightCord);

    // Hood volume resting on the back of the neck
    const hoodBackGeo = new THREE.SphereGeometry(0.24, 12, 10, 0, Math.PI * 2, 0, Math.PI * 0.6);
    const hoodBack = new THREE.Mesh(hoodBackGeo, hoodieMat);
    hoodBack.position.set(0, 0.82, 0.15);
    hoodBack.rotation.x = -0.5;
    this.bodyGroup.add(hoodBack);

    // Head
    const headGeo = new THREE.SphereGeometry(0.34, 18, 18);
    const head = new THREE.Mesh(headGeo, skinMat);
    head.position.y = 1.05;
    head.castShadow = true;
    this.bodyGroup.add(head);

    // Spiky anime hair
    const hairBaseGeo = new THREE.SphereGeometry(0.36, 16, 16, 0, Math.PI * 2, 0, Math.PI * 0.55);
    const hairBase = new THREE.Mesh(hairBaseGeo, hairMat);
    hairBase.position.y = 1.09;
    this.bodyGroup.add(hairBase);

    // Hair spikes / tufts
    const tuftGeo = new THREE.ConeGeometry(0.09, 0.22, 8);
    for (let t = -3; t <= 3; t++) {
      const tuft = new THREE.Mesh(tuftGeo, hairMat);
      tuft.position.set(t * 0.08, 1.25, -0.28 + Math.abs(t) * 0.04);
      tuft.rotation.x = 0.3;
      tuft.rotation.z = -t * 0.12;
      this.bodyGroup.add(tuft);
    }

    // Anime Eyes
    const eyeGeo = new THREE.SphereGeometry(0.065, 12, 12);
    const leftEye = new THREE.Mesh(eyeGeo, eyeMat);
    leftEye.position.set(-0.13, 1.05, -0.30);
    this.bodyGroup.add(leftEye);

    const rightEye = new THREE.Mesh(eyeGeo, eyeMat);
    rightEye.position.set(0.13, 1.05, -0.30);
    this.bodyGroup.add(rightEye);

    const glintGeo = new THREE.SphereGeometry(0.024, 8, 8);
    const leftGlint = new THREE.Mesh(glintGeo, glintMat);
    leftGlint.position.set(-0.11, 1.07, -0.35);
    this.bodyGroup.add(leftGlint);

    const rightGlint = new THREE.Mesh(glintGeo, glintMat);
    rightGlint.position.set(0.15, 1.07, -0.35);
    this.bodyGroup.add(rightGlint);

    // Articulated Arms (Sleeves + Elbows + Hands)
    const sleeveGeo = new THREE.CylinderGeometry(0.075, 0.07, 0.20, 8);
    const forearmGeo = new THREE.CylinderGeometry(0.065, 0.055, 0.18, 8);
    const fistGeo = new THREE.SphereGeometry(0.065, 8, 8);

    // Left Arm
    this.leftShoulder = new THREE.Group();
    this.leftShoulder.position.set(-0.32, 0.76, 0);
    const leftSleeve = new THREE.Mesh(sleeveGeo, hoodieMat);
    leftSleeve.position.y = -0.10;
    leftSleeve.castShadow = true;
    this.leftShoulder.add(leftSleeve);

    this.leftElbow = new THREE.Group();
    this.leftElbow.position.set(0, -0.20, 0);
    const leftForearm = new THREE.Mesh(forearmGeo, skinMat);
    leftForearm.position.y = -0.09;
    leftForearm.castShadow = true;
    this.leftElbow.add(leftForearm);

    const leftFist = new THREE.Mesh(fistGeo, skinMat);
    leftFist.position.y = -0.18;
    this.leftElbow.add(leftFist);

    this.leftShoulder.add(this.leftElbow);
    this.bodyGroup.add(this.leftShoulder);

    // Right Arm
    this.rightShoulder = new THREE.Group();
    this.rightShoulder.position.set(0.32, 0.76, 0);
    const rightSleeve = new THREE.Mesh(sleeveGeo, hoodieMat);
    rightSleeve.position.y = -0.10;
    rightSleeve.castShadow = true;
    this.rightShoulder.add(rightSleeve);

    this.rightElbow = new THREE.Group();
    this.rightElbow.position.set(0, -0.20, 0);
    const rightForearm = new THREE.Mesh(forearmGeo, skinMat);
    rightForearm.position.y = -0.09;
    rightForearm.castShadow = true;
    this.rightElbow.add(rightForearm);

    const rightFist = new THREE.Mesh(fistGeo, skinMat);
    rightFist.position.y = -0.18;
    this.rightElbow.add(rightFist);

    this.rightShoulder.add(this.rightElbow);
    this.bodyGroup.add(this.rightShoulder);

    // 2. Hip & Leg Hierarchy
    this.hipGroup = new THREE.Group();
    this.hipGroup.position.set(0, 0.38, 0);
    this.modelRoot.add(this.hipGroup);

    // Running Shorts Pelvis
    const shortsGeo = new THREE.BoxGeometry(0.46, 0.16, 0.32);
    const shorts = new THREE.Mesh(shortsGeo, shortsMat);
    shorts.position.y = 0.04;
    shorts.castShadow = true;
    this.hipGroup.add(shorts);

    const thighGeo = new THREE.CylinderGeometry(0.075, 0.07, 0.18, 8);
    const shinGeo = new THREE.CylinderGeometry(0.065, 0.06, 0.14, 8);
    const shoeBodyGeo = new THREE.BoxGeometry(0.16, 0.12, 0.25);
    const shoeSoleGeo = new THREE.BoxGeometry(0.17, 0.03, 0.27);
    const shoeToeGeo = new THREE.BoxGeometry(0.15, 0.07, 0.09);

    // Left Leg Hierarchy
    this.leftHip = new THREE.Group();
    this.leftHip.position.set(-0.15, 0, 0);
    const leftThigh = new THREE.Mesh(thighGeo, skinMat);
    leftThigh.position.y = -0.09;
    leftThigh.castShadow = true;
    this.leftHip.add(leftThigh);

    this.leftKnee = new THREE.Group();
    this.leftKnee.position.set(0, -0.18, 0);
    const leftShin = new THREE.Mesh(shinGeo, whiteMat);
    leftShin.position.y = -0.07;
    leftShin.castShadow = true;
    this.leftKnee.add(leftShin);

    this.leftFoot = new THREE.Group();
    this.leftFoot.position.set(0, -0.14, 0);

    const leftShoeBody = new THREE.Mesh(shoeBodyGeo, sneakerMat);
    leftShoeBody.position.set(0, 0.06, -0.04);
    leftShoeBody.castShadow = true;
    this.leftFoot.add(leftShoeBody);

    const leftShoeSole = new THREE.Mesh(shoeSoleGeo, whiteMat);
    leftShoeSole.position.set(0, 0.015, -0.04);
    this.leftFoot.add(leftShoeSole);

    const leftShoeToe = new THREE.Mesh(shoeToeGeo, whiteMat);
    leftShoeToe.position.set(0, 0.04, -0.14);
    this.leftFoot.add(leftShoeToe);

    this.leftKnee.add(this.leftFoot);
    this.leftHip.add(this.leftKnee);
    this.hipGroup.add(this.leftHip);

    // Right Leg Hierarchy
    this.rightHip = new THREE.Group();
    this.rightHip.position.set(0.15, 0, 0);
    const rightThigh = new THREE.Mesh(thighGeo, skinMat);
    rightThigh.position.y = -0.09;
    rightThigh.castShadow = true;
    this.rightHip.add(rightThigh);

    this.rightKnee = new THREE.Group();
    this.rightKnee.position.set(0, -0.18, 0);
    const rightShin = new THREE.Mesh(shinGeo, whiteMat);
    rightShin.position.y = -0.07;
    rightShin.castShadow = true;
    this.rightKnee.add(rightShin);

    this.rightFoot = new THREE.Group();
    this.rightFoot.position.set(0, -0.14, 0);

    const rightShoeBody = new THREE.Mesh(shoeBodyGeo, sneakerMat);
    rightShoeBody.position.set(0, 0.06, -0.04);
    rightShoeBody.castShadow = true;
    this.rightFoot.add(rightShoeBody);

    const rightShoeSole = new THREE.Mesh(shoeSoleGeo, whiteMat);
    rightShoeSole.position.set(0, 0.015, -0.04);
    this.rightFoot.add(rightShoeSole);

    const rightShoeToe = new THREE.Mesh(shoeToeGeo, whiteMat);
    rightShoeToe.position.set(0, 0.04, -0.14);
    this.rightFoot.add(rightShoeToe);

    this.rightKnee.add(this.rightFoot);
    this.rightHip.add(this.rightKnee);
    this.hipGroup.add(this.rightHip);

    // Clear twintails/skirt references for this model
    this.leftTwintail = null;
    this.rightTwintail = null;
    this.skirt = null;
  }

  /**
   * Loads the Meshy AI Blue Hoodie Runner 3D model in background.
   */
  loadMeshyCharacter() {
    const glbPath = 'assets/characters/character.glb';
    const objPath = 'assets/characters/Meshy_AI_Blue_Hoodie_Runner_1006071116_texture_obj/Meshy_AI_Blue_Hoodie_Runner_1006071116_texture.obj';
    const mtlPath = 'assets/characters/Meshy_AI_Blue_Hoodie_Runner_1006071116_texture_obj/Meshy_AI_Blue_Hoodie_Runner_1006071116_texture.mtl';

    if (typeof THREE.GLTFLoader !== 'undefined') {
      const loader = new THREE.GLTFLoader();
      loader.load(
        glbPath,
        (gltf) => {
          this.prepareCustomModel(gltf.scene);
        },
        undefined,
        () => {
          this.loadObjFallback(objPath, mtlPath);
        }
      );
    } else {
      this.loadObjFallback(objPath, mtlPath);
    }
  }

  loadObjFallback(objPath, mtlPath) {
    if (typeof THREE.MTLLoader !== 'undefined' && typeof THREE.OBJLoader !== 'undefined') {
      const mtlLoader = new THREE.MTLLoader();
      mtlLoader.load(mtlPath, (materials) => {
        materials.preload();
        const objLoader = new THREE.OBJLoader();
        objLoader.setMaterials(materials);
        objLoader.load(objPath, (object) => {
          this.prepareCustomModel(object);
        });
      });
    }
  }

  prepareCustomModel(model3D) {
    this.customModel = model3D;
    this.hasCustomModel = true;

    this.customModel.traverse((child) => {
      if (child.isMesh) {
        child.castShadow = true;
        child.receiveShadow = true;
        if (child.material) {
          child.material.roughness = 0.5;
          child.material.metalness = 0.05;
        }
      }
    });

    const box = new THREE.Box3().setFromObject(this.customModel);
    const height = box.max.y - box.min.y;
    const targetHeight = 1.62;
    const scale = targetHeight / (height || 1.9);
    this.customModel.scale.set(scale, scale, scale);

    const centeredBox = new THREE.Box3().setFromObject(this.customModel);
    this.customModel.position.x = -(centeredBox.min.x + centeredBox.max.x) / 2;
    this.customModel.position.y = -centeredBox.min.y;
    this.customModel.position.z = -(centeredBox.min.z + centeredBox.max.z) / 2;
    this.customModel.rotation.y = Math.PI;

    // Only attach if user explicitly chose meshy
    if (this.currentStyle === 'meshy') {
      while (this.characterContainer.children.length > 0) {
        this.characterContainer.remove(this.characterContainer.children[0]);
      }
      this.characterContainer.add(this.customModel);
    }
  }

  /**
   * Move player to the left lane
   */
  moveLeft() {
    if (this.currentLane > -1) {
      this.currentLane -= 1;
      this.targetX = this.currentLane * this.laneWidth;
    }
  }

  /**
   * Move player to the right lane
   */
  moveRight() {
    if (this.currentLane < 1) {
      this.currentLane += 1;
      this.targetX = this.currentLane * this.laneWidth;
    }
  }

  /**
   * Jump over obstacles
   */
  jump() {
    if (!this.isJumping) {
      this.velocityY = this.jumpForce;
      this.isJumping = true;
    }
  }

  /**
   * Main per-frame update for physics and animations
   * @param {number} delta - Frame delta time in seconds
   * @param {boolean} isRunning - Whether runner is moving forward
   * @param {number} speedRatio - Speed multiplier relative to base speed
   */
  update(delta, isRunning = true, speedRatio = 1.0) {
    // 1. Smooth horizontal lane movement
    this.mesh.position.x = THREE.MathUtils.lerp(
      this.mesh.position.x,
      this.targetX,
      Math.min(delta * 18, 1)
    );

    // Lateral leaning during lane shifts
    const xDiff = this.targetX - this.mesh.position.x;
    this.characterContainer.rotation.z = -xDiff * 0.28;

    // 2. Jump Physics with Gravity
    if (this.isJumping) {
      this.mesh.position.y += this.velocityY * delta;
      this.velocityY += this.gravity * delta;

      // Landing on road
      if (this.mesh.position.y <= this.groundY) {
        this.mesh.position.y = this.groundY;
        this.velocityY = 0;
        this.isJumping = false;
        this.landingSquash = 0.22; // Landing impact squash
      }
    }

    // Landing impact recovery (squash & stretch)
    if (this.landingSquash > 0) {
      this.landingSquash -= delta * 3.5;
      if (this.landingSquash < 0) this.landingSquash = 0;
      const squashY = 1.0 - this.landingSquash * 0.55;
      const stretchXZ = 1.0 + this.landingSquash * 0.28;
      this.characterContainer.scale.set(stretchXZ, squashY, stretchXZ);
    } else {
      this.characterContainer.scale.set(1, 1, 1);
    }

    // 3. Dynamic Animations
    if (this.currentStyle === 'meshy' && this.hasCustomModel) {
      this.updateMeshyAnimation(delta, isRunning, speedRatio, xDiff);
    } else {
      this.updateArticulatedAnimation(delta, isRunning, speedRatio, xDiff);
    }

    // Update collision bounding box
    this.updateBoundingBox();
  }

  /**
   * Articulated Multi-Joint Running Kinematics (Sweets Girl & Hoodie Runner)
   */
  updateArticulatedAnimation(delta, isRunning, speedRatio, xDiff) {
    if (isRunning) {
      // Dynamic stride frequency scaled with current running speed
      const runFrequency = 14.0 * Math.max(0.4, Math.min(speedRatio, 1.8));
      this.runTimer += delta * runFrequency;
      const cycle = this.runTimer;

      if (!this.isJumping) {
        // --- GROUND RUNNING STRIDE ---
        // Vertical step bounce (2 foot strikes per full stride cycle)
        const bounce = Math.abs(Math.sin(cycle)) * 0.065;
        this.bodyGroup.position.y = bounce;
        this.hipGroup.position.y = bounce * 0.5;

        // Torso sprint lean forward + slight pelvic twist
        this.bodyGroup.rotation.x = 0.14 + Math.abs(Math.sin(cycle)) * 0.03;
        this.bodyGroup.rotation.y = Math.sin(cycle) * 0.10;
        this.bodyGroup.rotation.z = Math.sin(cycle) * 0.04 - xDiff * 0.18;

        // Legs: Stride from hip joint
        const strideAngle = 0.75;
        this.leftHip.rotation.x = Math.sin(cycle) * strideAngle;
        this.rightHip.rotation.x = -Math.sin(cycle) * strideAngle;

        // Knees: Bend backward when swinging back, extend when reaching forward
        this.leftKnee.rotation.x = Math.max(0, -Math.sin(cycle)) * 0.95;
        this.rightKnee.rotation.x = Math.max(0, Math.sin(cycle)) * 0.95;

        // Ankles / Feet: Natural toe-tilt during stride
        this.leftFoot.rotation.x = -Math.sin(cycle) * 0.22;
        this.rightFoot.rotation.x = Math.sin(cycle) * 0.22;

        // Arms: Pump opposite to legs with bent elbows
        const armAngle = 0.78;
        this.leftShoulder.rotation.x = -Math.sin(cycle) * armAngle;
        this.rightShoulder.rotation.x = Math.sin(cycle) * armAngle;
        this.leftShoulder.rotation.z = -0.16 + Math.cos(cycle) * 0.05;
        this.rightShoulder.rotation.z = 0.16 - Math.cos(cycle) * 0.05;

        // Elbows: Bent in runner's sprint posture
        this.leftElbow.rotation.x = -0.85 + Math.sin(cycle) * 0.20;
        this.rightElbow.rotation.x = -0.85 - Math.sin(cycle) * 0.20;

        // Secondary Hair / Twintail Physics
        if (this.leftTwintail && this.rightTwintail) {
          this.leftTwintail.rotation.z = 0.42 + Math.sin(cycle * 2 + 0.3) * 0.16;
          this.leftTwintail.rotation.x = 0.22 - Math.sin(cycle) * 0.14;
          this.rightTwintail.rotation.z = -0.42 - Math.sin(cycle * 2 + 0.3) * 0.16;
          this.rightTwintail.rotation.x = 0.22 + Math.sin(cycle) * 0.14;
        }

        // Skirt flutter
        if (this.skirt) {
          this.skirt.rotation.x = Math.sin(cycle * 2) * 0.06;
          this.skirt.rotation.z = Math.sin(cycle) * 0.04;
        }
      } else {
        // --- JUMPING POSE ---
        this.bodyGroup.position.y = 0;
        this.hipGroup.position.y = 0;
        this.bodyGroup.rotation.x = -0.18;
        this.bodyGroup.rotation.y = 0;
        this.bodyGroup.rotation.z = -xDiff * 0.15;

        // Arms reach forward / up
        this.leftShoulder.rotation.set(-1.25, 0, -0.2);
        this.rightShoulder.rotation.set(-1.25, 0, 0.2);
        this.leftElbow.rotation.set(-0.35, 0, 0);
        this.rightElbow.rotation.set(-0.35, 0, 0);

        // Legs tuck up
        this.leftHip.rotation.set(-0.65, 0, 0);
        this.rightHip.rotation.set(-0.45, 0, 0);
        this.leftKnee.rotation.set(1.1, 0, 0);
        this.rightKnee.rotation.set(0.9, 0, 0);
        this.leftFoot.rotation.set(0.2, 0, 0);
        this.rightFoot.rotation.set(0.2, 0, 0);

        if (this.leftTwintail && this.rightTwintail) {
          this.leftTwintail.rotation.set(0.55, 0, 0.45);
          this.rightTwintail.rotation.set(0.55, 0, -0.45);
        }
      }
    } else {
      // --- IDLE / GAME OVER STANCE ---
      this.idleTimer += delta;
      const breathe = Math.sin(this.idleTimer * 2.5) * 0.02;

      this.bodyGroup.position.y = breathe;
      this.bodyGroup.rotation.set(0, 0, 0);
      this.hipGroup.position.y = 0;

      this.leftHip.rotation.set(0, 0, 0);
      this.rightHip.rotation.set(0, 0, 0);
      this.leftKnee.rotation.set(0, 0, 0);
      this.rightKnee.rotation.set(0, 0, 0);
      this.leftFoot.rotation.set(0, 0, 0);
      this.rightFoot.rotation.set(0, 0, 0);

      this.leftShoulder.rotation.set(0, 0, -0.12);
      this.rightShoulder.rotation.set(0, 0, 0.12);
      this.leftElbow.rotation.set(-0.30, 0, 0);
      this.rightElbow.rotation.set(-0.30, 0, 0);

      if (this.leftTwintail && this.rightTwintail) {
        this.leftTwintail.rotation.set(0, 0, 0.42 + Math.sin(this.idleTimer * 2) * 0.04);
        this.rightTwintail.rotation.set(0, 0, -0.42 - Math.sin(this.idleTimer * 2) * 0.04);
      }
    }
  }

  /**
   * Whole-body dynamics for Meshy AI 3D statue inspection
   */
  updateMeshyAnimation(delta, isRunning, speedRatio, xDiff) {
    if (isRunning) {
      const runFrequency = 14.0 * Math.max(0.4, Math.min(speedRatio, 1.8));
      this.runTimer += delta * runFrequency;
      const cycle = this.runTimer;

      if (!this.isJumping) {
        this.characterContainer.position.y = Math.abs(Math.sin(cycle)) * 0.09;
        this.characterContainer.rotation.z = -xDiff * 0.28 + Math.sin(cycle) * 0.06;
        this.characterContainer.rotation.x = 0.12 + Math.abs(Math.sin(cycle)) * 0.06;
        this.characterContainer.rotation.y = Math.sin(cycle) * 0.12;
      } else {
        this.characterContainer.position.y = 0;
        this.characterContainer.rotation.x = -0.22;
        this.characterContainer.rotation.z = -xDiff * 0.20;
        this.characterContainer.rotation.y = 0;
      }
    } else {
      this.characterContainer.position.y = 0;
      this.characterContainer.rotation.set(0, 0, 0);
    }
  }

  /**
   * Updates player's 3D bounding box for obstacle and collectible collisions
   */
  updateBoundingBox() {
    this.mesh.updateMatrixWorld(true);
    this.boundingBox.setFromObject(this.characterContainer);
    // Fair collision margins
    this.boundingBox.min.x += 0.08;
    this.boundingBox.max.x -= 0.08;
    this.boundingBox.min.z += 0.08;
    this.boundingBox.max.z -= 0.08;
  }

  /**
   * Reset player state for a fresh run
   */
  reset() {
    this.currentLane = 0;
    this.targetX = 0;
    this.mesh.position.set(0, this.groundY, 0);
    this.characterContainer.position.set(0, 0, 0);
    this.characterContainer.rotation.set(0, 0, 0);
    this.characterContainer.scale.set(1, 1, 1);
    this.velocityY = 0;
    this.isJumping = false;
    this.landingSquash = 0;
    this.runTimer = 0;
    this.idleTimer = 0;

    if (this.currentStyle !== 'meshy') {
      this.leftHip.rotation.set(0, 0, 0);
      this.rightHip.rotation.set(0, 0, 0);
      this.leftKnee.rotation.set(0, 0, 0);
      this.rightKnee.rotation.set(0, 0, 0);
      this.leftFoot.rotation.set(0, 0, 0);
      this.rightFoot.rotation.set(0, 0, 0);
      this.leftShoulder.rotation.set(0, 0, -0.12);
      this.rightShoulder.rotation.set(0, 0, 0.12);
      this.leftElbow.rotation.set(-0.30, 0, 0);
      this.rightElbow.rotation.set(-0.30, 0, 0);
      this.bodyGroup.position.set(0, 0, 0);
      this.bodyGroup.rotation.set(0, 0, 0);
      this.hipGroup.position.set(0, 0.38, 0);
      if (this.leftTwintail && this.rightTwintail) {
        this.leftTwintail.rotation.set(0, 0, 0.42);
        this.rightTwintail.rotation.set(0, 0, -0.42);
      }
    }

    this.updateBoundingBox();
  }
}
