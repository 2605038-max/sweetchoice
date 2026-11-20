# 3D Characters Asset Folder

### Active Character:
- **Meshy AI Blue Hoodie Runner**
  - Source archive: `Meshy_AI_Blue_Hoodie_Runner_1006071116_texture_obj.zip`
  - Extracted OBJ & Textures: `Meshy_AI_Blue_Hoodie_Runner_1006071116_texture_obj/`
  - Optimized binary GLTF: `character.glb` (5.0 MB, includes embedded textures & materials)

### How it works in `Sweet Run`:
In `js/player.js`:
1. `loadMeshyCharacter()` automatically loads `character.glb` via Three.js `GLTFLoader`.
2. Normalizes scale (target height: 1.65m), centers the character in the lane, aligns feet with ground level ($Y=0.55$), and faces the character forward along $-Z$.
3. Procedural running physics applies vertical bouncing, lane-steering banking, and jump tilting.
4. Falls back to `MTLLoader` + `OBJLoader` if required.
