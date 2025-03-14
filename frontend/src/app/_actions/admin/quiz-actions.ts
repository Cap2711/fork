'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';
import { Question } from '@/components/admin/questions/types';

interface Quiz {
  id: number;
  lesson_id: number;
  title: string;
  description: string;
  passing_score: number;
  time_limit: number | null;
  difficulty_level: string;
  is_published: boolean;
  questions: Question[];
  created_at: string;
  updated_at: string;
}

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(error: unknown): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

export async function getQuizzes() {
  try {
    const response = await axiosInstance.get<ApiResponse<Quiz[]>>('/admin/quizzes');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch quizzes'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getQuiz(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Quiz>>(`/admin/quizzes/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch quiz'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function createQuiz(form: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<Quiz>>('/admin/quizzes', {
      title: form.get('title'),
      description: form.get('description'),
      passing_score: form.get('passing_score'),
      time_limit: form.get('time_limit'),
      difficulty_level: form.get('difficulty_level'),
      is_published: form.get('is_published') === 'true',
      questions: JSON.parse(form.get('questions') as string),
      lesson_id: form.get('lesson_id'),
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create quiz'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateQuiz(id: number, form: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<Quiz>>(`/admin/quizzes/${id}`, {
      title: form.get('title'),
      description: form.get('description'),
      passing_score: form.get('passing_score'),
      time_limit: form.get('time_limit'),
      difficulty_level: form.get('difficulty_level'),
      is_published: form.get('is_published') === 'true',
      questions: JSON.parse(form.get('questions') as string),
      lesson_id: form.get('lesson_id'),
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update quiz'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteQuiz(id: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/quizzes/${id}`);
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete quiz'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

export async function toggleQuizStatus(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ is_published: boolean }>>(`/admin/quizzes/${id}/toggle-status`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to toggle quiz status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}