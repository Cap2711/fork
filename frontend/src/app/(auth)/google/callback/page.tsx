'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { handleOAuthResponse } from '@/app/_actions/auth-actions';

export default function GoogleCallbackPage() {
  const router = useRouter();

  useEffect(() => {
    const completeAuth = async () => {
      try {
        if (typeof window === 'undefined') return;

        // Get redirect path from OAuth response
        const redirectPath = await handleOAuthResponse(window.location.href);
        
        // Navigate to the appropriate path
        router.push(redirectPath);
      } catch (error) {
        console.error('Authentication error:', error);
        // Redirect to login on error
        router.push('/login?error=auth_failed');
      }
    };

    completeAuth();
  }, []); // Remove router from dependencies since it's stable

  return (
    <div className="flex items-center justify-center min-h-screen">
      <div className="text-center">
        <h2 className="mb-4 text-xl">Completing authentication...</h2>
        <div className="w-16 h-16 border-t-4 border-[var(--duo-blue)] border-solid rounded-full animate-spin mx-auto"></div>
      </div>
    </div>
  );
}