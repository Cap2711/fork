'use server';

import { revalidatePath } from 'next/cache';
import { SectionFormData } from '@/types/section';
import axiosInstance from '@/lib/axios';

function getErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  if (typeof error === 'object' && error && 'message' in error) {
    return String(error.message);
  }
  return 'An unexpected error occurred';
}

export async function createSection(formData: FormData) {
  try {
    const sectionData = {
      ...Object.fromEntries(formData.entries()),
      content: JSON.parse(formData.get('content') as string),
      practice_config: formData.get('practice_config')
        ? JSON.parse(formData.get('practice_config') as string)
        : null,
    };

    const response = await axiosInstance.post('/api/admin/sections', sectionData);
    revalidatePath('/admin/lessons/[id]', 'page');
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function updateSection(id: number, formData: FormData) {
  try {
    const sectionData = {
      ...Object.fromEntries(formData.entries()),
      content: JSON.parse(formData.get('content') as string),
      practice_config: formData.get('practice_config')
        ? JSON.parse(formData.get('practice_config') as string)
        : null,
    };

    const response = await axiosInstance.put(`/api/admin/sections/${id}`, sectionData);
    revalidatePath('/admin/lessons/[id]', 'page');
    revalidatePath('/admin/sections/[id]', 'page');
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function deleteSection(id: number) {
  try {
    await axiosInstance.delete(`/api/admin/sections/${id}`);
    revalidatePath('/admin/lessons/[id]', 'page');
    return { success: true };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function getSection(id: number) {
  try {
    const response = await axiosInstance.get(`/api/admin/sections/${id}`);
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function updateSectionOrder(id: number, order: number) {
  try {
    const response = await axiosInstance.patch(`/api/admin/sections/${id}/order`, { order });
    revalidatePath('/admin/lessons/[id]', 'page');
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function toggleSectionPublished(id: number) {
  try {
    const response = await axiosInstance.patch(`/api/admin/sections/${id}/toggle-published`);
    revalidatePath('/admin/lessons/[id]', 'page');
    revalidatePath('/admin/sections/[id]', 'page');
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

// Convert SectionFormData to FormData for API submission
export function prepareSectionFormData(data: SectionFormData): FormData {
  const formData = new FormData();
  
  // Add basic fields
  formData.append('title', data.title);
  formData.append('description', data.description);
  formData.append('type', data.type);
  formData.append('order', data.order.toString());
  formData.append('lesson_id', data.lesson_id.toString());
  formData.append('content', JSON.stringify(data.content));
  
  if (data.practice_config) {
    formData.append('practice_config', JSON.stringify(data.practice_config));
  }
  
  formData.append('requires_previous', data.requires_previous.toString());
  formData.append('xp_reward', data.xp_reward.toString());
  formData.append('estimated_time', data.estimated_time.toString());
  
  if (data.min_correct_required !== null) {
    formData.append('min_correct_required', data.min_correct_required.toString());
  }
  
  formData.append('allow_retry', data.allow_retry.toString());
  formData.append('show_solution', data.show_solution.toString());
  formData.append('is_published', data.is_published.toString());
  formData.append('difficulty_level', data.difficulty_level);
  
  return formData;
}