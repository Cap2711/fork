'use server';

import { NextResponse } from 'next/server';
import axiosInstance from '@/lib/axios';
import { UserRole } from '@/types/user';

interface AuthResponse {
  token: string;
  user: {
    id: number;
    name: string;
    email: string;
    role: UserRole;
  };
}

interface AdminInviteResponse {
  message: string;
  invite_url: string;
}

interface ApiError {
  message: string;
  status: number;
}

interface ErrorResult {
  error: string;
  success?: never;
}

interface SuccessResult {
  error?: never;
  success: true;
  redirect: string;
}

type AuthResult = ErrorResult | SuccessResult;

function isAxiosError(error: unknown): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

function setAuthCookies(response: AuthResponse): void {
  // Create response with cookies
  const res = new NextResponse();

  // Set cookies
  res.cookies.set({
    name: 'token',
    value: response.token,
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
  });

  res.cookies.set({
    name: 'user_data',
    value: JSON.stringify({
      id: response.user.id,
      name: response.user.name,
      email: response.user.email,
      role: response.user.role
    }),
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
  });
}

export async function login(formData: FormData): Promise<AuthResult> {
  try {
    const response = await axiosInstance.post<AuthResponse>('/auth/login', {
      email: formData.get('email'),
      password: formData.get('password'),
    });

    // Set cookies
    setAuthCookies(response.data);

    // Return success with redirect URL
    return { 
      success: true, 
      redirect: response.data.user.role === UserRole.ADMIN ? '/admin' : '/learn'
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return { error: error.response?.data?.message || 'Login failed' };
    }
    return { error: 'An unexpected error occurred' };
  }
}

export async function register(formData: FormData): Promise<AuthResult> {
  try {
    const response = await axiosInstance.post<AuthResponse>('/auth/register', {
      name: formData.get('name'),
      email: formData.get('email'),
      password: formData.get('password'),
      password_confirmation: formData.get('password_confirmation'),
      invite_token: formData.get('invite_token'),
    });

    // Set cookies
    setAuthCookies(response.data);

    // Return success with redirect URL
    return { 
      success: true, 
      redirect: response.data.user.role === UserRole.ADMIN ? '/admin' : '/learn'
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return { error: error.response?.data?.message || 'Registration failed' };
    }
    return { error: 'An unexpected error occurred' };
  }
}

export async function sendAdminInvite(email: string) {
  try {
    const response = await axiosInstance.post<AdminInviteResponse>('/admin/invite', {
      email,
    });

    return { success: true, data: response.data };
  } catch (error) {
    if (isAxiosError(error)) {
      return { success: false, error: error.response?.data?.message || 'Failed to send invite' };
    }
    return { success: false, error: 'An unexpected error occurred' };
  }
}

export async function validateInvite(token: string) {
  try {
    const response = await axiosInstance.post('/admin/invite/validate', {
      token,
    });

    return { success: true, data: response.data };
  } catch (error) {
    if (isAxiosError(error)) {
      return { success: false, error: error.response?.data?.message || 'Invalid invite' };
    }
    return { success: false, error: 'An unexpected error occurred' };
  }
}

export async function logout() {
  try {
    await axiosInstance.post('/auth/logout');
  } catch (error) {
    console.error('Logout error:', error);
  }

  const res = new NextResponse();

  // Clear cookies
  res.cookies.set({
    name: 'token',
    value: '',
    maxAge: 0,
  });
  res.cookies.set({
    name: 'user_data',
    value: '',
    maxAge: 0,
  });

  // Create redirect response
  const baseUrl = process.env.NEXT_PUBLIC_URL || 'http://localhost:3000';
  return NextResponse.redirect(new URL('/login', baseUrl));
}

export async function getGoogleAuthUrl() {
  return `${process.env.NEXT_PUBLIC_API_URL}/auth/google`;
}
