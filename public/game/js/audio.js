/**
 * Sweet Run - Sound Engine (js/audio.js)
 * 
 * Synthesizes retro candy-themed sound effects using the standard Web Audio API.
 * No external sound files or network requests required!
 */

class SoundEngine {
  constructor() {
    this.ctx = null;
    this.isMuted = false;
  }

  init() {
    if (!this.ctx) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (AudioCtx) {
        this.ctx = new AudioCtx();
      }
    }
    if (this.ctx && this.ctx.state === 'suspended') {
      this.ctx.resume();
    }
  }

  toggleMute() {
    this.isMuted = !this.isMuted;
    return this.isMuted;
  }

  /**
   * Cute bouncy jump sound
   */
  playJump() {
    if (this.isMuted || !this.ctx) return;
    try {
      const now = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'triangle';
      osc.frequency.setValueAtTime(220, now);
      osc.frequency.exponentialRampToValueAtTime(560, now + 0.18);

      gain.gain.setValueAtTime(0.25, now);
      gain.gain.exponentialRampToValueAtTime(0.01, now + 0.22);

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + 0.22);
    } catch (e) {}
  }

  /**
   * Sweet candy collect sound (higher chime for gold items)
   */
  playCollect(isGold = false) {
    if (this.isMuted || !this.ctx) return;
    try {
      const now = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'sine';
      const baseFreq = isGold ? 880 : 587.33; // A5 or D5
      osc.frequency.setValueAtTime(baseFreq, now);
      osc.frequency.setValueAtTime(baseFreq * 1.5, now + 0.08); // Jump to fifth

      gain.gain.setValueAtTime(0.2, now);
      gain.gain.exponentialRampToValueAtTime(0.01, now + (isGold ? 0.35 : 0.22));

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + (isGold ? 0.35 : 0.22));
    } catch (e) {}
  }

  /**
   * Warning siren when Cake-Eating Monster approaches
   */
  playWarning() {
    if (this.isMuted || !this.ctx) return;
    try {
      const now = this.ctx.currentTime;
      for (let i = 0; i < 3; i++) {
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        const start = now + (i * 0.15);

        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(440, start);
        osc.frequency.linearRampToValueAtTime(660, start + 0.12);

        gain.gain.setValueAtTime(0.18, start);
        gain.gain.exponentialRampToValueAtTime(0.01, start + 0.14);

        osc.connect(gain);
        gain.connect(this.ctx.destination);

        osc.start(start);
        osc.stop(start + 0.14);
      }
    } catch (e) {}
  }

  /**
   * Hungry monster cartoon roar / rumble
   */
  playMonsterRoar() {
    if (this.isMuted || !this.ctx) return;
    try {
      const now = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(110, now);
      osc.frequency.linearRampToValueAtTime(80, now + 0.3);
      osc.frequency.linearRampToValueAtTime(130, now + 0.6);

      gain.gain.setValueAtTime(0.25, now);
      gain.gain.exponentialRampToValueAtTime(0.01, now + 0.65);

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + 0.65);
    } catch (e) {}
  }

  /**
   * Cartoon bonk on obstacle collision
   */
  playHit() {
    if (this.isMuted || !this.ctx) return;
    try {
      const now = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'square';
      osc.frequency.setValueAtTime(160, now);
      osc.frequency.exponentialRampToValueAtTime(50, now + 0.25);

      gain.gain.setValueAtTime(0.3, now);
      gain.gain.exponentialRampToValueAtTime(0.01, now + 0.28);

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + 0.28);
    } catch (e) {}
  }
}

// Global sound singleton
window.soundEngine = new SoundEngine();
