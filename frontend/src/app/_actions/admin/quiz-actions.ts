'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface QuizQuestion {
  id?: number;
  question: string;
  correct_answer: string;
  options: string[];
  explanation: string;
  order: number;
}

interface Quiz {
  id: number;
  lesson_id: number;
  title: string;
  description: string;
  passing_score: number;
  time_limit: number | null;
  difficulty_level: string;
  is_published: boolean;
  questions: QuizQuestion[];
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

export async function createQuiz(formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<Quiz>>('/admin/quizzes', {
      title: formData.get('title'),
      description: formData.get('description'),
      passing_score: formData.get('passing_score'),
      time_limit: formData.get('time_limit'),
      difficulty_level: formData.get('difficulty_level'),
      is_published: formData.get('is_published') === 'true',
      questions: JSON.parse(formData.get('questions') as string)
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

export async function updateQuiz(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<Quiz>>(`/admin/quizzes/${id}`, {
      title: formData.get('title'),
      description: formData.get('description'),
      passing_score: formData.get('passing_score'),
      time_limit: formData.get('time_limit'),
      difficulty_level: formData.get('difficulty_level'),
      is_published: formData.get('is_published') === 'true',
      questions: JSON.parse(formData.get('questions') as string)
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