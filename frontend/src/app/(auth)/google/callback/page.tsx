'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { handleOAuthResponse } from '@/app/_actions/auth-actions';

export default function GoogleCallbackPage() {
  const router = useRouter();

  useEffect(() => {
    // Handle the OAuth callback
    const completeAuth = async () => {
      if (typeof window !== 'undefined') {
        await handleOAuthResponse(window.location.href);
      }
    };

    completeAuth();
  }, [router]);

  return (
    <div className="flex items-center justify-center min-h-screen">
      <div className="text-center">
        <h2 className="mb-4 text-xl">Completing authentication...</h2>
        <div className="w-16 h-16 border-t-4 border-[var(--duo-blue)] border-solid rounded-full animate-spin mx-auto"></div>
      </div>
    </div>
  );
}