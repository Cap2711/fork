'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface Lesson {
  id: number;
  unit_id: number;
  title: string;
  description: string;
  content: string;
  order: number;
  estimated_duration: number;
  difficulty_level: string;
  is_published: boolean;
  has_quiz: boolean;
  has_exercise: boolean;
  has_vocabulary: boolean;
  created_at: string;
  updated_at: string;
}

interface LessonContent {
  id: number;
  content_type: 'quiz' | 'exercise' | 'vocabulary';
  content_id: number;
  order: number;
}

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(error: unknown): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

export async function getLessons(unitId?: number) {
  try {
    const url = unitId 
      ? `/admin/units/${unitId}/lessons`
      : '/admin/lessons';
    
    const response = await axiosInstance.get<ApiResponse<Lesson[]>>(url);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch lessons'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getLesson(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Lesson>>(`/admin/lessons/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch lesson'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getLessonContents(lessonId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<LessonContent[]>>(`/admin/lessons/${lessonId}/contents`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch lesson contents'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function createLesson(unitId: number, formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<Lesson>>(`/admin/units/${unitId}/lessons`, {
      title: formData.get('title'),
      description: formData.get('description'),
      content: formData.get('content'),
      order: formData.get('order'),
      estimated_duration: formData.get('estimated_duration'),
      difficulty_level: formData.get('difficulty_level'),
      is_published: formData.get('is_published') === 'true'
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create lesson'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateLesson(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<Lesson>>(`/admin/lessons/${id}`, {
      title: formData.get('title'),
      description: formData.get('description'),
      content: formData.get('content'),
      order: formData.get('order'),
      estimated_duration: formData.get('estimated_duration'),
      difficulty_level: formData.get('difficulty_level'),
      is_published: formData.get('is_published') === 'true'
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update lesson'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteLesson(id: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/lessons/${id}`);
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete lesson'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

export async function toggleLessonStatus(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ is_published: boolean }>>(`/admin/lessons/${id}/toggle-status`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to toggle lesson status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function reorderLessons(unitId: number, lessonOrders: { id: number; order: number }[]) {
  try {
    const response = await axiosInstance.put<ApiResponse<{ success: boolean }>>(
      `/admin/units/${unitId}/lessons/reorder`,
      { lessons: lessonOrders }
    );
    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to reorder lessons'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}

export async function attachContent(
  lessonId: number, 
  contentType: 'quiz' | 'exercise' | 'vocabulary',
  contentId: number,
  order: number
) {
  try {
    const response = await axiosInstance.post<ApiResponse<LessonContent>>(
      `/admin/lessons/${lessonId}/contents`,
      {
        content_type: contentType,
        content_id: contentId,
        order: order
      }
    );
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to attach content to lesson'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function detachContent(lessonId: number, contentId: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/lessons/${lessonId}/contents/${contentId}`);
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to detach content from lesson'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

export async function reorderContents(lessonId: number, contentOrders: { id: number; order: number }[]) {
  try {
    const response = await axiosInstance.put<ApiResponse<{ success: boolean }>>(
      `/admin/lessons/${lessonId}/contents/reorder`,
      { contents: contentOrders }
    );
    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to reorder lesson contents'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}