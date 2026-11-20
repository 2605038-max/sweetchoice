export type GameState = 'START' | 'PLAYING' | 'PAUSED' | 'GAME_OVER';

export type CharacterStyle = 'sweets' | 'hoodie' | 'meshy';

export type PowerUpType = 'magnet' | 'shield';

export interface ActivePowerUp {
  type: PowerUpType;
  duration: number;
  maxDuration: number;
}

export interface FloatingScore {
  id: number;
  text: string;
  isGold: boolean;
  x: number;
  y: number;
}

export interface GameMetrics {
  score: number;
  treats: number;
  distance: number;
  speedMultiplier: number;
  activePowerUps?: ActivePowerUp[];
}

