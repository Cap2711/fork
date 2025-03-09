'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { UserRole } from '@/types/user';

interface UserProfile {
  role: UserRole;
}

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();

  useEffect(() => {
    const checkAdminAccess = async () => {
      try {
        const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/auth/user`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        });
        
        if (!res.ok) {
          router.push('/login');
          return;
        }

        const user: UserProfile = await res.json();
        if (user.role !== 'admin') {
          router.push('/dashboard');
        }
      } catch (err) {
        console.error('Error checking admin access:', err);
        router.push('/dashboard');
      }
    };

    checkAdminAccess();
  }, [router]);

  return <>{children}</>;
}