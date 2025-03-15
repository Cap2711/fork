'use client';

import { useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { handleGoogleCallback } from '@/app/_actions/auth-actions';
import { toast } from 'sonner';

export default function GoogleCallbackPage() {
  const router = useRouter();
  const searchParams = useSearchParams();

  useEffect(() => {
    const handleCallback = async () => {
      try {
        const code = searchParams.get('code');
        if (!code) {
          toast.error('Authentication failed', {
            description: 'No authorization code found',
          });
          router.push('/login');
          return;
        }

        const redirectPath = await handleGoogleCallback(code);
        router.push(redirectPath);
      } catch {
        toast.error('Authentication failed', {
          description: 'Could not complete sign in with Google',
        });
        router.push('/login');
      }
    };

    handleCallback();
  }, [router, searchParams]);

  // No need for complex UI, this is just a transition page
  return null;
}