'use client';

import Link from 'next/link';
import { useState } from 'react';
import { login } from '@/app/_actions/auth-actions';

export default function LoginPage() {
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleEmailLogin = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const result = await login(new FormData(e.currentTarget));
      if (result?.error) {
        setError(result.error);
      }
    } catch {
      setError('Failed to login');
    } finally {
      setLoading(false);
    }
  };

  const handleGoogleLogin = () => {
    // Handle both localhost and IP address scenarios
    const currentHost = window.location.hostname;
    const isLocalhost = currentHost === 'localhost';
    const baseUrl = process.env.NEXT_PUBLIC_API_URL;
    const redirectUrl = isLocalhost ? 'http://localhost:3000' : `http://${currentHost}:3000`;
    
    window.location.href = `${baseUrl}/auth/google?redirect_url=${encodeURIComponent(redirectUrl)}`;
  };

  return (
    <div className="space-y-6">
      {/* Google Sign In */}
      <button
        type="button"
        onClick={handleGoogleLogin}
        className="w-full bg-[#4285f4] text-white duo-button border-[#357abd] hover:bg-[#357abd] flex items-center justify-center gap-2"
      >
        <span className="material-icons-outlined">
          google
        </span>
        Continue with Google
      </button>

      {/* Divider */}
      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-gray-300"></div>
        </div>
        <div className="relative flex justify-center text-sm">
          <span className="px-2 bg-white text-gray-500">or</span>
        </div>
      </div>

      {/* Email Login Form */}
      <form onSubmit={handleEmailLogin} className="space-y-4">
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm">
            {error}
          </div>
        )}

        <div>
          <label htmlFor="email" className="block text-sm font-bold text-gray-700">
            Email address
          </label>
          <div className="mt-1">
            <input
              id="email"
              name="email"
              type="email"
              autoComplete="email"
              required
              className="duo-input"
              placeholder="student@example.com"
            />
          </div>
        </div>

        <div>
          <label htmlFor="password" className="block text-sm font-bold text-gray-700">
            Password
          </label>
          <div className="mt-1">
            <input
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              className="duo-input"
              placeholder="••••••••"
            />
          </div>
        </div>

        <div>
          <button
            type="submit"
            disabled={loading}
            className="w-full duo-button"
          >
            {loading ? 'Logging in...' : 'Start Learning!'}
          </button>
        </div>
      </form>

      <div className="text-center space-y-4">
        <p className="text-sm text-gray-600">
          {"Don't have an account? "}
          <Link 
            href="/register" 
            className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
          >
            Sign up for free
          </Link>
        </p>

        <Link 
          href="/forgot-password"
          className="block text-sm font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
        >
          Forgot your password?
        </Link>
      </div>
    </div>
  );
}