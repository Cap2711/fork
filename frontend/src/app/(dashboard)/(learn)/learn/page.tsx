'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/app/providers/auth-provider';
import { UserRole } from '@/types/user';
import UnitModal from './unit-modal';

interface UserProgress {
  total_exercises: number;
  completed_exercises: number;
  points: number;
  streak_days: number;
  current_unit: number;
}

interface Unit {
  id: number;
  name: string;
  description: string;
  total_lessons: number;
  completed_lessons: number;
  locked: boolean;
  difficulty: 'beginner' | 'intermediate' | 'advanced';
  level: number;
}

interface Lesson {
  id: number;
  title: string;
  description: string;
  completed: boolean;
  xp: number;
}

const DEMO_LESSONS: Lesson[] = [
  {
    id: 1,
    title: "Greetings",
    description: "Learn basic greetings and introductions",
    completed: true,
    xp: 10
  },
  {
    id: 2,
    title: "Basic Phrases",
    description: "Essential everyday expressions",
    completed: true,
    xp: 10
  },
  {
    id: 3,
    title: "Numbers",
    description: "Count and use numbers",
    completed: false,
    xp: 15
  },
  {
    id: 4,
    title: "Simple Questions",
    description: "Ask and answer basic questions",
    completed: false,
    xp: 15
  }
];

function UnitCircle({ unit, onClick }: { unit: Unit; onClick: () => void }) {
  const getDifficultyColor = (difficulty: Unit['difficulty']) => {
    switch (difficulty) {
      case 'beginner': return 'var(--duo-green)';
      case 'intermediate': return 'var(--duo-blue)';
      case 'advanced': return 'var(--duo-purple)';
    }
  };

  const color = getDifficultyColor(unit.difficulty);
  const progress = (unit.completed_lessons / unit.total_lessons) * 100;
  
  return (
    <div className="relative">
      {/* Circle Progress Background */}
      <div className="relative w-24 h-24 mx-auto">
        {/* Background Circle */}
        <svg className="absolute w-full h-full -rotate-90">
          <circle
            cx="48"
            cy="48"
            r="44"
            fill="transparent"
            stroke="#e5e7eb"
            strokeWidth="8"
          />
          {!unit.locked && progress > 0 && (
            <circle
              cx="48"
              cy="48"
              r="44"
              fill="transparent"
              stroke={color}
              strokeWidth="8"
              strokeDasharray={`${progress * 2.77} 277`}
              className="transition-all duration-500"
            />
          )}
        </svg>

        {/* Unit Circle */}
        <div 
          className="absolute flex items-center justify-center transition-transform transform rounded-full shadow-lg cursor-pointer inset-2 hover:scale-105"
          style={{ background: color, opacity: unit.locked ? 0.5 : 1 }}
          onClick={onClick}
        >
          <span className="text-xl font-bold text-white">{unit.id}</span>
        </div>

        {/* Crown for completed units */}
        {unit.level > 0 && (
          <div className="absolute flex items-center justify-center w-8 h-8 text-white transform -translate-x-1/2 bg-yellow-400 rounded-full shadow-lg -top-2 left-1/2">
            <span className="text-sm material-icons-outlined">stars</span>
          </div>
        )}
      </div>

      {/* Unit Info */}
      <div className="mt-4 text-center">
        <h3 className="text-lg font-bold">{unit.name}</h3>
        <p className="text-sm text-gray-600">{unit.description}</p>
        {!unit.locked && (
          <div className="mt-2 text-sm">
            {unit.completed_lessons}/{unit.total_lessons} lessons
          </div>
        )}
        {unit.locked && (
          <div className="flex items-center justify-center gap-1 mt-2 text-sm text-gray-500">
            <span className="text-sm material-icons-outlined">lock</span>
            <span>Locked</span>
          </div>
        )}
      </div>
    </div>
  );
}

const UNITS: Unit[] = [
  {
    id: 1,
    name: "Basics 1",
    description: "Simple sentences and greetings",
    total_lessons: 5,
    completed_lessons: 3,
    locked: false,
    difficulty: 'beginner',
    level: 2
  },
  {
    id: 2,
    name: "Basics 2",
    description: "Basic phrases and vocabulary",
    total_lessons: 5,
    completed_lessons: 1,
    locked: false,
    difficulty: 'beginner',
    level: 0
  },
  {
    id: 3,
    name: "Common Words",
    description: "Essential vocabulary",
    total_lessons: 6,
    completed_lessons: 0,
    locked: true,
    difficulty: 'beginner',
    level: 0
  },
  {
    id: 4,
    name: "Phrases",
    description: "Useful everyday phrases",
    total_lessons: 7,
    completed_lessons: 0,
    locked: true,
    difficulty: 'intermediate',
    level: 0
  },
];

export default function Learn() {
  const router = useRouter();
  const { user: authUser } = useAuth();
  const [progress, setProgress] = useState<UserProgress>({
    total_exercises: 0,
    completed_exercises: 0,
    points: 0,
    streak_days: 0,
    current_unit: 1
  });
  const [loading, setLoading] = useState(true);
  const [selectedUnit, setSelectedUnit] = useState<Unit | null>(null);

  const fetchData = useCallback(async () => {
    try {
      const headers = { 'Content-Type': 'application/json' };
      const [progressRes] = await Promise.all([
        fetch(`/api/user/progress`, { headers }),
      ]);

      if (!progressRes.ok) throw new Error('Failed to fetch data');
      const progressData = await progressRes.json();
      setProgress(progressData);
      setLoading(false);
    } catch (err) {
      console.error('Error fetching dashboard data:', err);
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!authUser) {
      router.push('/login');
      return;
    }
    if (authUser.role === UserRole.ADMIN) {
      router.push('/admin');
      return;
    }
    fetchData();
  }, [authUser, router, fetchData]);

  if (loading || !authUser) return null;

  return (
    <>
      <div className="max-w-5xl px-4 py-6 mx-auto">
        {/* Top Stats Bar */}
        <div className="flex items-center justify-between p-4 mb-8 bg-white shadow-sm rounded-xl">
          <div className="flex items-center gap-6">
            <div className="flex items-center gap-2">
              <span className="material-icons-outlined text-[var(--duo-yellow)]">local_fire_department</span>
              <span className="font-bold">{progress.streak_days} day streak</span>
            </div>
            <div className="flex items-center gap-2">
              <span className="material-icons-outlined text-[var(--duo-green)]">diamond</span>
              <span className="font-bold">{progress.points} points</span>
            </div>
          </div>
          <button 
            onClick={() => router.push('/learn/practice')}
            className="duo-button bg-[var(--duo-green)] text-white"
          >
            Practice All
          </button>
        </div>

        {/* Learning Path */}
        <div className="relative">
          {/* Completed Path Line */}
          <div className="absolute left-0 right-0 h-2 bg-gray-200 top-1/2 -z-10">
            <div 
              className="h-full bg-[var(--duo-green)]"
              style={{ 
                width: `${(progress.completed_exercises / progress.total_exercises) * 100}%`,
                transition: 'width 0.5s ease-in-out'
              }}
            />
          </div>

          {/* Units */}
          <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            {UNITS.map((unit) => (
              <UnitCircle
                key={unit.id}
                unit={unit}
                onClick={() => !unit.locked && setSelectedUnit(unit)}
              />
            ))}
          </div>
        </div>
      </div>

      {/* Unit Modal */}
      {selectedUnit && (
        <UnitModal
          unit={selectedUnit}
          lessons={DEMO_LESSONS}
          isOpen={!!selectedUnit}
          onClose={() => setSelectedUnit(null)}
        />
      )}
    </>
  );
}
