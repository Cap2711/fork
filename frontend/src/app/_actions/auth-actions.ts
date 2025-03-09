'use server';

import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
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
    // Set user data in an encrypted cookie
    // @ts-expect-error Server Component
    cookies().set('user_data', JSON.stringify({
      id: response.data.user.id,
      name: response.data.user.name,
      email: response.data.user.email,
      role: response.data.user.role
    }), { httpOnly: true, secure: process.env.NODE_ENV === 'production' });


    // Redirect based on user role
    if (response.data.user.role === UserRole.ADMIN) {
      redirect('/admin');
    } else {
      redirect('/learn');
    }
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
      invite_token: formData.get('invite_token'), // Optional invite token for admin registration
    });

    // @ts-expect-error Server Component
    cookies().set('token', response.data.token);
    // Set user data in an encrypted cookie
    // @ts-expect-error Server Component
    cookies().set('user_data', JSON.stringify({
      id: response.data.user.id,
      name: response.data.user.name,
      email: response.data.user.email,
      role: response.data.user.role
    }), { httpOnly: true, secure: process.env.NODE_ENV === 'production' });

    // Redirect based on user role
    if (response.data.user.role === UserRole.ADMIN) {
      redirect('/admin');
    } else {
      redirect('/learn');
    }
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

  // @ts-expect-error Server Component
  cookies().set('token', '');
  // @ts-expect-error Server Component
  cookies().set('user_data', '');
  redirect('/login');
}

export async function getGoogleAuthUrl() {
  return `${process.env.NEXT_PUBLIC_API_URL}/auth/google`;
}
