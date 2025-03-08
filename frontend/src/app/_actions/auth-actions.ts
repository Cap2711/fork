'use server';

import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
// import axios from 'axios';
import axiosInstance from '@/lib/axios';

interface AuthResponse {
  token: string;
  user: {
    id: number;
    name: string;
    email: string;
  };
}

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(error: unknown): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

export async function login(formData: FormData) {
  try {
    const response = await axiosInstance.post<AuthResponse>('/auth/login', {
      email: formData.get('email'),
      password: formData.get('password'),
    });

    // @ts-expect-error Server Component
    cookies().set('token', response.data.token);
    redirect('/dashboard');
  } catch (error) {
    if (isAxiosError(error)) {
      return { error: error.response?.data?.message || 'Login failed' };
    }
    return { error: 'An unexpected error occurred' };
  }
}

export async function register(formData: FormData) {
  try {
    const response = await axiosInstance.post<AuthResponse>('/auth/register', {
      name: formData.get('name'),
      email: formData.get('email'),
      password: formData.get('password'),
      password_confirmation: formData.get('password_confirmation'),
    });

    // @ts-expect-error Server Component
    cookies().set('token', response.data.token);
    redirect('/dashboard');
  } catch (error) {
    if (isAxiosError(error)) {
      return { error: error.response?.data?.message || 'Registration failed' };
    }
    return { error: 'An unexpected error occurred' };
  }
}

export async function logout() {
  try {
    await axiosInstance.post('/auth/logout');
  } catch (error) {
    console.error('Logout error:', error);
  }

  // @ts-expect-error Server Component
  cookies().set('token', '');
  redirect('/login');
}

export function getGoogleAuthUrl() {
  return `${process.env.NEXT_PUBLIC_API_URL}/auth/google`;
}
