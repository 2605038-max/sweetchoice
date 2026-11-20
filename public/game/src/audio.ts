/**
 * Sweet Run - Sound Engine (src/audio.ts)
 * 
 * Synthesizes retro candy-themed sound effects using the standard Web Audio API.
 * No external sound files or network requests required.
 */

export class SoundEngine {
  private ctx: AudioContext | null = null;
  public isMuted: boolean = false;

  init() {
    if (!this.ctx) {
      const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
      if (AudioCtx) {
        this.ctx = new AudioCtx();
      }
    }
    if (this.ctx && this.ctx.state === 'suspended') {
      this.ctx.resume();
    }
  }

  toggleMute(): boolean {
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
    } catch {
      // AudioContext error suppressed
    }
  }

  /**
   * Smooth cute sliding whoosh sound
   */
  playSlide() {
    if (this.isMuted || !this.ctx) return;
    try {
      const now = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(480, now);
      osc.frequency.exponentialRampToValueAtTime(180, now + 0.22);

      gain.gain.setValueAtTime(0.2, now);
      gain.gain.exponentialRampToValueAtTime(0.01, now + 0.24);

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + 0.24);
    } catch {
      // AudioContext error suppressed
    }
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
    } catch {
      // AudioContext error suppressed
    }
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
    } catch {
      // AudioContext error suppressed
    }
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
    } catch {
      // AudioContext error suppressed
    }
  }

  /**
   * Magical ascending arpeggio on collecting a power-up (Magnet or Shield)
   */
  playPowerUpCollect(isShield: boolean = false) {
    if (this.isMuted || !this.ctx) return;
    try {
      const now = this.ctx.currentTime;
      const notes = isShield ? [523.25, 659.25, 783.99, 1046.5] : [440, 554.37, 659.25, 880];
      notes.forEach((freq, idx) => {
        if (!this.ctx) return;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        const start = now + idx * 0.06;

        osc.type = isShield ? 'triangle' : 'sine';
        osc.frequency.setValueAtTime(freq, start);

        gain.gain.setValueAtTime(0.22, start);
        gain.gain.exponentialRampToValueAtTime(0.01, start + 0.2);

        osc.connect(gain);
        gain.connect(this.ctx.destination);

        osc.start(start);
        osc.stop(start + 0.2);
      });
    } catch {
      // AudioContext error suppressed
    }
  }

  /**
   * Sound when shield absorbs a hit or shatters
   */
  playShieldPop() {
    if (this.isMuted || !this.ctx) return;
    try {
      const now = this.ctx.currentTime;
      // High glass/sugar chime + low blast
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'triangle';
      osc.frequency.setValueAtTime(980, now);
      osc.frequency.exponentialRampToValueAtTime(240, now + 0.35);

      gain.gain.setValueAtTime(0.35, now);
      gain.gain.exponentialRampToValueAtTime(0.01, now + 0.35);

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + 0.35);
    } catch {
      // AudioContext error suppressed
    }
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
    } catch {
      // AudioContext error suppressed
    }
  }
}

export const soundEngine = new SoundEngine();
