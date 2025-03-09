'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { UserRole } from '@/types/user';

interface UserProfile {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  points: number;
}

export default function LearnLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const [user, setUser] = useState<UserProfile | null>(null);

  useEffect(() => {
    const checkAuth = async () => {
      const token = localStorage.getItem('token');
      if (!token) {
        router.push('/login');
        return;
      }

      try {
        const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/auth/user`, {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        });
        
        if (!res.ok) {
          router.push('/login');
          return;
        }

        const data = await res.json();
        if (data.role === 'admin') {
          router.push('/admin');
          return;
        }
        
        setUser(data);
      } catch (err) {
        console.error('Error checking auth:', err);
        router.push('/login');
      }
    };

    checkAuth();
  }, [router]);

  if (!user) return null;

  return <>{children}</>;
}
