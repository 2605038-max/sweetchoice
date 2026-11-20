import React, { useEffect, useRef, useState, useCallback } from 'react';
import { GameEngine } from './gameEngine';
import { soundEngine } from './audio';
import { GameState, CharacterStyle, FloatingScore, GameMetrics } from './types';
import { Collectible } from './collectibles';

export const App: React.FC = () => {
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const engineRef = useRef<GameEngine | null>(null);

  const [gameState, setGameState] = useState<GameState>('START');
  const [metrics, setMetrics] = useState<GameMetrics>({
    score: 0,
    treats: 0,
    distance: 0,
    speedMultiplier: 1.0
  });
  const [monsterWarning, setMonsterWarning] = useState<boolean>(false);
  const [isMuted, setIsMuted] = useState<boolean>(false);
  const [characterStyle, setCharacterStyle] = useState<CharacterStyle>('sweets');
  const [floatingScores, setFloatingScores] = useState<FloatingScore[]>([]);

  // Initialize Three.js game engine
  useEffect(() => {
    if (!canvasRef.current) return;

    const engine = new GameEngine(canvasRef.current, {
      onMetricsUpdate: (newMetrics) => {
        setMetrics(newMetrics);
      },
      onWarning: (show) => {
        setMonsterWarning(show);
      },
      onPickup: (item: Collectible) => {
        const id = Date.now() + Math.random();
        let text = `+${item.points}`;
        if (item.type === 'magnet') {
          text = '🧲 MAGNET!';
        } else if (item.type === 'shield') {
          text = '🛡️ SHIELD!';
        }

        setFloatingScores((prev) => [
          ...prev,
          { id, text, isGold: item.isGold, x: 50, y: 45 }
        ]);

        setTimeout(() => {
          setFloatingScores((prev) => prev.filter((s) => s.id !== id));
        }, 850);
      },
      onGameOver: () => {
        setGameState('GAME_OVER');
      }
    });

    engineRef.current = engine;

    return () => {
      engine.dispose();
      engineRef.current = null;
    };
  }, []);

  // Sync character style to engine
  const handleSelectCharacter = (style: CharacterStyle) => {
    setCharacterStyle(style);
    if (engineRef.current) {
      engineRef.current.setCharacterStyle(style);
    }
  };

  const handleStart = () => {
    soundEngine.init();
    if (engineRef.current) {
      engineRef.current.startGame();
    }
    setGameState('PLAYING');
  };

  const handleRestart = () => {
    soundEngine.init();
    if (engineRef.current) {
      engineRef.current.restartGame();
    }
    setGameState('PLAYING');
  };

  const handlePause = useCallback(() => {
    if (gameState === 'PLAYING') {
      if (engineRef.current) {
        engineRef.current.pauseGame();
      }
      setGameState('PAUSED');
    }
  }, [gameState]);

  const handleResume = useCallback(() => {
    if (gameState === 'PAUSED') {
      if (engineRef.current) {
        engineRef.current.resumeGame();
      }
      setGameState('PLAYING');
    }
  }, [gameState]);

  const handleToggleAudio = () => {
    soundEngine.init();
    const muted = soundEngine.toggleMute();
    setIsMuted(muted);
  };

  // Keyboard navigation
  const handleKeyDown = useCallback(
    (e: KeyboardEvent) => {
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' '].includes(e.key)) {
        e.preventDefault();
      }

      if (e.key === 'p' || e.key === 'P' || e.key === 'Escape') {
        if (gameState === 'PLAYING') {
          handlePause();
          return;
        } else if (gameState === 'PAUSED') {
          handleResume();
          return;
        }
      }

      if (gameState === 'PAUSED' && (e.key === ' ' || e.key === 'Enter')) {
        handleResume();
        return;
      }

      if (gameState === 'START' && (e.key === ' ' || e.key === 'Enter')) {
        handleStart();
        return;
      }
      if (gameState === 'GAME_OVER' && (e.key === ' ' || e.key === 'Enter')) {
        handleRestart();
        return;
      }

      if (gameState === 'PLAYING' && engineRef.current) {
        switch (e.key) {
          case 'ArrowLeft':
          case 'a':
          case 'A':
            engineRef.current.moveLeft();
            break;
          case 'ArrowRight':
          case 'd':
          case 'D':
            engineRef.current.moveRight();
            break;
          case 'ArrowUp':
          case 'w':
          case 'W':
          case ' ':
            engineRef.current.jump();
            break;
          case 'ArrowDown':
          case 's':
          case 'S':
            engineRef.current.slide();
            break;
        }
      }
    },
    [gameState, handlePause, handleResume]
  );

  useEffect(() => {
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [handleKeyDown]);

  // Touch Swipe controls
  useEffect(() => {
    let touchStartX = 0;
    let touchStartY = 0;

    const handleTouchStart = (e: TouchEvent) => {
      soundEngine.init();
      if (e.touches.length > 0) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
      }
    };

    const handleTouchEnd = (e: TouchEvent) => {
      if (e.changedTouches.length > 0 && gameState === 'PLAYING' && engineRef.current) {
        const deltaX = e.changedTouches[0].clientX - touchStartX;
        const deltaY = e.changedTouches[0].clientY - touchStartY;
        const minSwipe = 28;

        if (Math.abs(deltaX) > Math.abs(deltaY)) {
          if (deltaX > minSwipe) {
            engineRef.current.moveRight();
          } else if (deltaX < -minSwipe) {
            engineRef.current.moveLeft();
          }
        } else {
          if (deltaY < -minSwipe) {
            engineRef.current.jump();
          } else if (deltaY > minSwipe) {
            engineRef.current.slide();
          }
        }
      }
    };

    window.addEventListener('touchstart', handleTouchStart, { passive: true });
    window.addEventListener('touchend', handleTouchEnd, { passive: true });

    return () => {
      window.removeEventListener('touchstart', handleTouchStart);
      window.removeEventListener('touchend', handleTouchEnd);
    };
  }, [gameState]);

  return (
    <div id="game-container">
      {/* 3D WebGL Canvas */}
      <canvas id="game-canvas" ref={canvasRef} />

      {/* In-Game HUD */}
      <div id="hud">
        <div className="hud-group">
          <div className="hud-badge">
            <span>⭐</span>
            <span className="label">Score</span>
            <span id="score-val" className="value">{Math.floor(metrics.score)}</span>
          </div>
          <div className="hud-badge">
            <span>🍰</span>
            <span className="label">Treats</span>
            <span id="treats-val" className="value">{metrics.treats}</span>
          </div>
        </div>
        <div className="hud-group">
          <div className="hud-badge">
            <span>🏃</span>
            <span className="label">Distance</span>
            <span id="distance-val" className="value">{Math.floor(metrics.distance)}m</span>
          </div>
          <div className="hud-badge">
            <span>⚡</span>
            <span className="label">Speed</span>
            <span id="speed-val" className="value">{metrics.speedMultiplier.toFixed(1)}x</span>
          </div>
          {gameState === 'PLAYING' && (
            <button
              id="pause-btn"
              className="audio-btn"
              title="Pause Game (P or Esc)"
              onClick={handlePause}
              aria-label="Pause Game"
            >
              ⏸️
            </button>
          )}
          <button
            id="audio-toggle-btn"
            className="audio-btn"
            title="Toggle Sound"
            onClick={handleToggleAudio}
          >
            {isMuted ? '🔇' : '🔊'}
          </button>
        </div>
      </div>

      {/* Active Power-Ups HUD Display */}
      {metrics.activePowerUps && metrics.activePowerUps.length > 0 && (
        <div className="active-powerups-bar">
          {metrics.activePowerUps.map((p) => {
            const pct = Math.max(0, Math.min(100, (p.duration / p.maxDuration) * 100));
            const isMagnet = p.type === 'magnet';
            return (
              <div key={p.type} className={`powerup-pill ${p.type}`}>
                <span className="powerup-icon">{isMagnet ? '🧲' : '🛡️'}</span>
                <div className="powerup-info">
                  <div className="powerup-header">
                    <span className="powerup-name">{isMagnet ? 'MAGNET' : 'SHIELD'}</span>
                    <span className="powerup-timer">{p.duration.toFixed(1)}s</span>
                  </div>
                  <div className="powerup-progress-bg">
                    <div className="powerup-progress-fill" style={{ width: `${pct}%` }} />
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Cake-Eating Monster Warning Banner */}
      <div id="monster-warning" className={monsterWarning ? '' : 'hidden'}>
        <span>⚠️</span> HUNGRY MONSTER! <span>🍰</span>
      </div>

      {/* Floating Pickup Scores */}
      <div id="floating-scores">
        {floatingScores.map((item) => (
          <div
            key={item.id}
            className={`floating-score${item.isGold ? ' gold' : ''}`}
            style={{ left: `${item.x}%`, top: `${item.y}%` }}
          >
            {item.text}
          </div>
        ))}
      </div>

      {/* Mobile Screen Virtual Controls for Touch Users */}
      {gameState === 'PLAYING' && (
        <div className="mobile-controls">
          <button
            className="mobile-btn"
            onClick={() => engineRef.current?.moveLeft()}
            aria-label="Move Left"
          >
            ←
          </button>
          <button
            className="mobile-btn"
            onClick={() => engineRef.current?.slide()}
            aria-label="Slide Down"
          >
            ↓
          </button>
          <button
            className="mobile-btn"
            onClick={() => engineRef.current?.jump()}
            aria-label="Jump Up"
          >
            ↑
          </button>
          <button
            className="mobile-btn"
            onClick={() => engineRef.current?.moveRight()}
            aria-label="Move Right"
          >
            →
          </button>
        </div>
      )}

      {/* Start Screen Overlay */}
      <div id="start-screen" className={`ui-screen ${gameState !== 'START' ? 'hidden' : ''}`}>
        <div className="card">
          <h1 className="game-title">🍬 Sweet Run 🍰</h1>
          <p className="subtitle">An original 3D candy endless runner</p>

          <div className="character-picker">
            <button
              className={`character-badge ${characterStyle === 'sweets' ? 'active' : ''}`}
              onClick={() => handleSelectCharacter('sweets')}
            >
              🍬 Sweets Girl
            </button>
            <button
              className={`character-badge ${characterStyle === 'hoodie' ? 'active' : ''}`}
              onClick={() => handleSelectCharacter('hoodie')}
            >
              🏃 Blue Hoodie
            </button>
          </div>

          <div className="instructions-box">
            <h3>How to Play</h3>
            <div className="control-item">
              <span>Move Left / Right</span>
              <span className="key-badge">← / → or A / D</span>
            </div>
            <div className="control-item">
              <span>Jump Low Hurdles</span>
              <span className="key-badge">Space or ↑</span>
            </div>
            <div className="control-item">
              <span>Slide Under High Barriers</span>
              <span className="key-badge">↓ or S</span>
            </div>
            <div className="control-item">
              <span>Pause Game</span>
              <span className="key-badge">P or Esc</span>
            </div>
            <div className="control-item">
              <span>🧲 Magnet & 🛡️ Shield</span>
              <span className="key-badge">Power-Ups</span>
            </div>
          </div>

          <button id="start-btn" className="candy-btn" onClick={handleStart}>
            Start Game 🚀
          </button>
        </div>
      </div>

      {/* Pause Screen Overlay */}
      <div id="pause-screen" className={`ui-screen ${gameState !== 'PAUSED' ? 'hidden' : ''}`}>
        <div className="card">
          <h1 className="game-title">Paused ⏸️</h1>
          <p className="subtitle">Catch your breath, sweet runner!</p>

          <div className="game-over-stats">
            <div className="stat-item">
              <span className="stat-label">Current Score</span>
              <span className="stat-value">{Math.floor(metrics.score)}</span>
            </div>
            <div className="stat-item">
              <span className="stat-label">Treats</span>
              <span className="stat-value">{metrics.treats}</span>
            </div>
            <div className="stat-item">
              <span className="stat-label">Distance</span>
              <span className="stat-value">{Math.floor(metrics.distance)}m</span>
            </div>
          </div>

          <div className="pause-actions">
            <button id="resume-btn" className="candy-btn" onClick={handleResume}>
              Resume ▶️
            </button>
            <button id="pause-restart-btn" className="candy-btn secondary-btn" onClick={handleRestart}>
              Restart 🔄
            </button>
          </div>
        </div>
      </div>

      {/* Game Over Screen Overlay */}
      <div id="game-over-screen" className={`ui-screen ${gameState !== 'GAME_OVER' ? 'hidden' : ''}`}>
        <div className="card">
          <h1 className="game-title">Game Over! 💥</h1>
          <p className="subtitle">You crashed into a sweet obstacle!</p>

          <div className="game-over-stats">
            <div className="stat-item">
              <span className="stat-label">Final Score</span>
              <span id="final-score-val" className="stat-value">{Math.floor(metrics.score)}</span>
            </div>
            <div className="stat-item">
              <span className="stat-label">Treats</span>
              <span id="final-treats-val" className="stat-value">{metrics.treats}</span>
            </div>
            <div className="stat-item">
              <span className="stat-label">Distance</span>
              <span id="final-distance-val" className="stat-value">{Math.floor(metrics.distance)}m</span>
            </div>
          </div>

          <button id="restart-btn" className="candy-btn" onClick={handleRestart}>
            Play Again 🔄
          </button>
        </div>
      </div>
    </div>
  );
};
