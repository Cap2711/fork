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
              strokeDasharray={`${2 * Math.PI * 44 * progress / 100} ${2 * Math.PI * 44}`}
              className="transition-all duration-500"
            />
          )}
        </svg>

        {/* Unit Circle */}
        <div 
          className="absolute inset-2 rounded-full flex items-center justify-center cursor-pointer transform hover:scale-105 transition-transform shadow-lg"
          style={{ background: color, opacity: unit.locked ? 0.5 : 1 }}
          onClick={onClick}
        >
          <span className="text-white text-xl font-bold">{unit.id}</span>
        </div>

        {/* Crown for completed units */}
        {unit.level > 0 && (
          <div className="absolute -top-2 left-1/2 transform -translate-x-1/2 bg-yellow-400 text-white rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
            <span className="material-icons-outlined text-sm">stars</span>
          </div>
        )}
      </div>

      {/* Unit Info */}
      <div className="mt-4 text-center">
        <h3 className="font-bold text-lg">{unit.name}</h3>
        <p className="text-sm text-gray-600">{unit.description}</p>
        {!unit.locked && (
          <div className="mt-2 text-sm">
            {unit.completed_lessons}/{unit.total_lessons} lessons
          </div>
        )}
        {unit.locked && (
          <div className="mt-2 flex items-center justify-center gap-1 text-sm text-gray-500">
            <span className="material-icons-outlined text-sm">lock</span>
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
        fetch(`${process.env.NEXT_PUBLIC_API_URL}/user/progress`, { headers }),
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
      <div className="max-w-5xl mx-auto px-4 py-6">
        {/* Top Stats Bar */}
        <div className="flex items-center justify-between mb-8 bg-white p-4 rounded-xl shadow-sm">
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
          <div className="absolute top-1/2 left-0 right-0 h-2 bg-gray-200 -z-10">
            <div 
              className="h-full bg-[var(--duo-green)]"
              style={{ 
                width: `${(progress.current_unit / UNITS.length) * 100}%`,
                transition: 'width 0.5s ease-in-out'
              }}
            />
          </div>

          {/* Units */}
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
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
