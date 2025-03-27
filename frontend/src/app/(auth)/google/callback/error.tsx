'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

export default function Error() {
  const router = useRouter();

  useEffect(() => {
    toast.error('Authentication failed', {
      description: 'An error occurred during sign in',
    });
    router.push('/login');
  }, [router]);

  return null;
}