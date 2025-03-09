'use server';

import axiosInstance, { ApiResponse } from '@/lib/axios';

export interface LessonData {
    title: string;
    description: string;
    type: 'mixed' | 'vocabulary' | 'grammar' | 'reading';
    order: number;
    xp_reward: number;
}

export type LessonUpdateData = Partial<LessonData>;

export interface LessonStats {
    total_attempts: number;
    completion_count: number;
    completion_rate: number;
    average_score: number;
    average_completion_time: number;
    exercises_count: number;
}

export interface LessonExercise {
    id: number;
    exercise_type: string;
    prompt: string;
    order: number;
    xp_reward: number;
}

export interface LessonResponse extends LessonData {
    id: number;
    unit_id: number;
    exercises: LessonExercise[];
    stats: LessonStats;
}

export async function listLessons(
    unitId: number
): Promise<ApiResponse<{ lessons: LessonResponse[] }>> {
    try {
        const response = await axiosInstance.get<ApiResponse<{ lessons: LessonResponse[] }>>(
            `/admin/units/${unitId}/lessons`
        );
        return response.data;
    } catch (error) {
        console.error('Error fetching lessons:', error);
        throw error;
    }
}

export async function getLessonStats(
    lessonId: number
): Promise<ApiResponse<{ stats: LessonStats }>> {
    try {
        const response = await axiosInstance.get<ApiResponse<{ stats: LessonStats }>>(
            `/admin/lessons/${lessonId}`
        );
        return response.data;
    } catch (error) {
        console.error('Error fetching lesson stats:', error);
        throw error;
    }
}

export async function createLesson(
    unitId: number,
    data: LessonData
): Promise<ApiResponse<{ lesson: LessonResponse }>> {
    try {
        const response = await axiosInstance.post<ApiResponse<{ lesson: LessonResponse }>>(
            `/admin/units/${unitId}/lessons`,
            data
        );
        return response.data;
    } catch (error) {
        console.error('Error creating lesson:', error);
        throw error;
    }
}

export async function updateLesson(
    lessonId: number,
    data: LessonUpdateData
): Promise<ApiResponse<{ lesson: LessonResponse }>> {
    try {
        const response = await axiosInstance.put<ApiResponse<{ lesson: LessonResponse }>>(
            `/admin/lessons/${lessonId}`,
            data
        );
        return response.data;
    } catch (error) {
        console.error('Error updating lesson:', error);
        throw error;
    }
}

export async function deleteLesson(
    lessonId: number
): Promise<ApiResponse<{ message: string }>> {
    try {
        const response = await axiosInstance.delete<ApiResponse<{ message: string }>>(
            `/admin/lessons/${lessonId}`
        );
        return response.data;
    } catch (error) {
        console.error('Error deleting lesson:', error);
        throw error;
    }
}

export async function cloneLesson(
    lessonId: number
): Promise<ApiResponse<{ lesson: LessonResponse }>> {
    try {
        const response = await axiosInstance.post<ApiResponse<{ lesson: LessonResponse }>>(
            `/admin/lessons/${lessonId}/clone`
        );
        return response.data;
    } catch (error) {
        console.error('Error cloning lesson:', error);
        throw error;
    }
}

export async function reorderLessons(
    unitId: number,
    lessonOrders: { id: number; order: number }[]
): Promise<ApiResponse<{ lessons: LessonResponse[] }>> {
    try {
        const response = await axiosInstance.put<ApiResponse<{ lessons: LessonResponse[] }>>(
            `/admin/units/${unitId}/lessons/reorder`,
            { lessons: lessonOrders }
        );
        return response.data;
    } catch (error) {
        console.error('Error reordering lessons:', error);
        throw error;
    }
}