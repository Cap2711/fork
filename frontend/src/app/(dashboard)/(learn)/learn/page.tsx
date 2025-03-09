'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { UserRole } from '@/types/user';

interface UserProgress {
  total_exercises: number;
  completed_exercises: number;
  points: number;
  streak_days: number;
}

interface UserProfile {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  points: number;
}

export default function Dashboard() {
  const router = useRouter();
  const [user, setUser] = useState<UserProfile | null>(null);
  const [progress, setProgress] = useState<UserProgress>({
    total_exercises: 0,
    completed_exercises: 0,
    points: 0,
    streak_days: 0
  });

  useEffect(() => {
    fetchUserProfile();
    fetchProgress();
  }, []);

  const fetchUserProfile = async () => {
    try {
      const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/auth/user`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      
      if (!res.ok) {
        if (res.status === 401) {
          router.push('/login');
          return;
        }
        throw new Error('Failed to fetch user profile');
      }

      const data = await res.json();
      setUser(data);

      // Redirect admins to admin dashboard
      if (data.role === 'admin') {
        router.push('/admin');
      }
    } catch (err) {
      console.error('Error fetching user profile:', err);
    }
  };

  const fetchProgress = async () => {
    try {
      const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/user/progress`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      if (!res.ok) throw new Error('Failed to fetch progress');
      const data = await res.json();
      setProgress(data);
    } catch (err) {
      console.error('Error fetching progress:', err);
    }
  };

  if (!user) return null;

  return (
    <div className="space-y-8">
      {/* Welcome Section */}
      <div className="bg-white p-6 rounded-xl shadow-sm">
        <h1 className="text-2xl font-bold mb-2">Welcome back, {user.name}!</h1>
        <p className="text-gray-600">Continue your learning journey today.</p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-sm text-gray-500 mb-1">Total Points</div>
          <div className="text-2xl font-bold text-[var(--duo-green)]">
            {progress.points}
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-sm text-gray-500 mb-1">Day Streak</div>
          <div className="text-2xl font-bold text-[var(--duo-yellow)]">
            {progress.streak_days} days
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-sm text-gray-500 mb-1">Exercises Completed</div>
          <div className="text-2xl font-bold text-[var(--duo-blue)]">
            {progress.completed_exercises}
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-sm text-gray-500 mb-1">Completion Rate</div>
          <div className="text-2xl font-bold text-[var(--duo-purple)]">
            {progress.total_exercises > 0
              ? Math.round((progress.completed_exercises / progress.total_exercises) * 100)
              : 0}%
          </div>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="bg-white p-6 rounded-xl shadow-sm">
        <h2 className="text-xl font-semibold mb-4">Recent Activity</h2>
        <div className="text-center text-gray-500 py-8">
          Start learning to see your activity here!
        </div>
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <button className="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow text-left group">
          <h3 className="font-semibold group-hover:text-[var(--duo-green)]">Practice Grammar</h3>
          <p className="text-sm text-gray-500 mt-1">Improve your language skills</p>
        </button>

        <button className="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow text-left group">
          <h3 className="font-semibold group-hover:text-[var(--duo-blue)]">Reading Comprehension</h3>
          <p className="text-sm text-gray-500 mt-1">Challenge yourself with texts</p>
        </button>

        <button className="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow text-left group">
          <h3 className="font-semibold group-hover:text-[var(--duo-purple)]">Vocabulary Review</h3>
          <p className="text-sm text-gray-500 mt-1">Learn new words</p>
        </button>
      </div>
    </div>
  );
}