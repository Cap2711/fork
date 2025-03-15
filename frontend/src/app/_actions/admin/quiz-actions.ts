'use server';

import { revalidatePath } from 'next/cache';
import axiosInstance from '@/lib/axios';
import { Quiz, QuizFormData, QuizSettings } from '@/types/quiz';

function getErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  if (typeof error === 'object' && error && 'message' in error) {
    return String(error.message);
  }
  return 'An unexpected error occurred';
}

const getDefaultSettings = (type: 'lesson_assessment' | 'section_practice'): QuizSettings => ({
  show_feedback: true,
  allow_retry: true,
  shuffle_questions: true,
  show_progress: true,
  require_passing_grade: type === 'lesson_assessment',
  unlock_next_lesson: type === 'lesson_assessment',
  immediate_feedback: type === 'section_practice',
  hints_enabled: type === 'section_practice',
  practice_mode: type === 'section_practice',
});

export async function createQuiz(formData: Partial<QuizFormData>) {
  try {
    const type = formData.type || 'lesson_assessment';
    
    // Transform the form data to match API expectations
    const quizData = {
      lesson_id: formData.lesson_id,
      section_id: formData.section_id,
      title: formData.title || '',
      description: formData.description || '',
      type,
      questions: formData.questions || [],
      passing_score: formData.passing_score || 70,
      time_limit: formData.time_limit,
      xp_reward: formData.xp_reward || 50,
      order: formData.order || 0,
      is_published: formData.is_published || false,
      settings: formData.settings || getDefaultSettings(type),
    };

    const response = await axiosInstance.post<Quiz>('/api/admin/quizzes', quizData);
    revalidatePath('/admin/lessons/[id]', 'page');
    return { data: response.data, error: null };
  } catch (error) {
    return { data: null, error: getErrorMessage(error) };
  }
}

export async function updateQuiz(id: number, formData: Partial<QuizFormData>) {
  try {
    const response = await axiosInstance.put<Quiz>(`/api/admin/quizzes/${id}`, formData);
    revalidatePath('/admin/lessons/[id]', 'page');
    return { data: response.data, error: null };
  } catch (error) {
    return { data: null, error: getErrorMessage(error) };
  }
}

export async function deleteQuiz(id: number) {
  try {
    await axiosInstance.delete(`/api/admin/quizzes/${id}`);
    revalidatePath('/admin/lessons/[id]', 'page');
    return { error: null };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function getQuiz(id: number) {
  try {
    const response = await axiosInstance.get<Quiz>(`/api/admin/quizzes/${id}`);
    return { data: response.data, error: null };
  } catch (error) {
    return { data: null, error: getErrorMessage(error) };
  }
}

export async function updateQuizOrder(id: number, order: number) {
  try {
    const response = await axiosInstance.patch<Quiz>(`/api/admin/quizzes/${id}/order`, { order });
    revalidatePath('/admin/lessons/[id]', 'page');
    return { data: response.data, error: null };
  } catch (error) {
    return { data: null, error: getErrorMessage(error) };
  }
}

export async function toggleQuizPublished(id: number) {
  try {
    const response = await axiosInstance.patch<Quiz>(`/api/admin/quizzes/${id}/toggle-published`);
    revalidatePath('/admin/lessons/[id]', 'page');
    return { data: response.data, error: null };
  } catch (error) {
    return { data: null, error: getErrorMessage(error) };
  }
}

// Alias for backward compatibility
export const toggleQuizStatus = toggleQuizPublished;