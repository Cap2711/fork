'use server';

import axiosInstance, { ApiResponse } from '@/lib/axios';
import {
    ExerciseContent,
    ExerciseAnswer,
    ExerciseDistractors
} from './exercise-types';

export interface ExerciseTypeData {
    name: string;
    description: string;
    component_name: string;
}

export interface ExerciseData {
    exercise_type_id: number;
    prompt: string;
    content: ExerciseContent;
    correct_answer: ExerciseAnswer;
    distractors?: ExerciseDistractors;
    order: number;
    xp_reward: number;
    hints?: ExerciseHintData[];
}

export interface ExerciseHintData {
    hint: string;
    order: number;
    xp_penalty: number;
}

export type ExerciseUpdateData = Partial<ExerciseData>;

export interface ExerciseTypeStats {
    total_exercises: number;
    total_attempts: number;
    completion_rate: number;
    average_score: number;
}

export interface ExerciseTypeResponse extends ExerciseTypeData {
    id: number;
    usage_stats: ExerciseTypeStats;
}

export interface ExerciseStats {
    total_attempts: number;
    completion_count: number;
    completion_rate: number;
    average_score: number;
    average_attempts: number;
    hint_usage_rate: number;
}

export interface ExerciseResponse extends Omit<ExerciseData, 'hints'> {
    id: number;
    lesson_id: number;
    stats: ExerciseStats;
    hints: Array<ExerciseHintData & { id: number }>;
}

export interface ExerciseTemplate {
    id: number;
    name: string;
    description: string;
    structure: {
        prompt: string;
        content: ExerciseContent;
        correct_answer: ExerciseAnswer;
        distractors?: ExerciseDistractors;
    };
}

// Exercise Type Management
export async function listExerciseTypes(): Promise<ApiResponse<{ types: ExerciseTypeResponse[] }>> {
    try {
        const response = await axiosInstance.get<ApiResponse<{ types: ExerciseTypeResponse[] }>>(
            '/admin/exercises/types'
        );
        return response.data;
    } catch (error) {
        console.error('Error fetching exercise types:', error);
        throw error;
    }
}

export async function createExerciseType(
    data: ExerciseTypeData
): Promise<ApiResponse<{ type: ExerciseTypeResponse }>> {
    try {
        const response = await axiosInstance.post<ApiResponse<{ type: ExerciseTypeResponse }>>(
            '/admin/exercises/types',
            data
        );
        return response.data;
    } catch (error) {
        console.error('Error creating exercise type:', error);
        throw error;
    }
}

export async function updateExerciseType(
    typeId: number,
    data: Partial<ExerciseTypeData>
): Promise<ApiResponse<{ type: ExerciseTypeResponse }>> {
    try {
        const response = await axiosInstance.put<ApiResponse<{ type: ExerciseTypeResponse }>>(
            `/admin/exercises/types/${typeId}`,
            data
        );
        return response.data;
    } catch (error) {
        console.error('Error updating exercise type:', error);
        throw error;
    }
}

// Exercise Management
export async function createExercise(
    lessonId: number,
    data: ExerciseData
): Promise<ApiResponse<{ exercise: ExerciseResponse }>> {
    try {
        const response = await axiosInstance.post<ApiResponse<{ exercise: ExerciseResponse }>>(
            `/admin/exercises/${lessonId}`,
            data
        );
        return response.data;
    } catch (error) {
        console.error('Error creating exercise:', error);
        throw error;
    }
}

export async function updateExercise(
    exerciseId: number,
    data: ExerciseUpdateData
): Promise<ApiResponse<{ exercise: ExerciseResponse }>> {
    try {
        const response = await axiosInstance.put<ApiResponse<{ exercise: ExerciseResponse }>>(
            `/admin/exercises/${exerciseId}`,
            data
        );
        return response.data;
    } catch (error) {
        console.error('Error updating exercise:', error);
        throw error;
    }
}

export async function deleteExercise(
    exerciseId: number
): Promise<ApiResponse<{ message: string }>> {
    try {
        const response = await axiosInstance.delete<ApiResponse<{ message: string }>>(
            `/admin/exercises/${exerciseId}`
        );
        return response.data;
    } catch (error) {
        console.error('Error deleting exercise:', error);
        throw error;
    }
}

export async function cloneExercise(
    exerciseId: number
): Promise<ApiResponse<{ exercise: ExerciseResponse }>> {
    try {
        const response = await axiosInstance.post<ApiResponse<{ exercise: ExerciseResponse }>>(
            `/admin/exercises/${exerciseId}/clone`
        );
        return response.data;
    } catch (error) {
        console.error('Error cloning exercise:', error);
        throw error;
    }
}

// Exercise Templates
export async function listExerciseTemplates(
    typeId: number
): Promise<ApiResponse<{ templates: ExerciseTemplate[] }>> {
    try {
        const response = await axiosInstance.get<ApiResponse<{ templates: ExerciseTemplate[] }>>(
            `/admin/exercises/types/${typeId}/templates`
        );
        return response.data;
    } catch (error) {
        console.error('Error fetching exercise templates:', error);
        throw error;
    }
}

export async function createExerciseFromTemplate(
    lessonId: number,
    templateId: number,
    data: Partial<ExerciseData>
): Promise<ApiResponse<{ exercise: ExerciseResponse }>> {
    try {
        const response = await axiosInstance.post<ApiResponse<{ exercise: ExerciseResponse }>>(
            `/admin/exercises/${lessonId}/from-template/${templateId}`,
            data
        );
        return response.data;
    } catch (error) {
        console.error('Error creating exercise from template:', error);
        throw error;
    }
}