'use server';

import axiosInstance, { ApiResponse } from '@/lib/axios';

export interface UnitData {
    name: string;
    description: string;
    difficulty: 'beginner' | 'intermediate' | 'advanced';
    order: number;
    is_locked?: boolean;
}

export type UnitUpdateData = Partial<UnitData>;

export interface UnitStats {
    completion_rate: number;
    avg_completion_time: number;
    total_users: number;
    completed_users: number;
}

export interface UnitLesson {
    id: number;
    title: string;
    description: string;
    type: 'mixed' | 'vocabulary' | 'grammar' | 'reading';
    order: number;
    xp_reward: number;
    exercises_count: number;
}

export interface UnitResponse extends UnitData {
    id: number;
    lessons: UnitLesson[];
    stats: UnitStats;
}

export type UnitOrderData = { id: number; order: number }[];

export async function listUnits(): Promise<ApiResponse<{ units: UnitResponse[] }>> {
    try {
        const response = await axiosInstance.get<ApiResponse<{ units: UnitResponse[] }>>('/admin/units');
        return response.data;
    } catch (error) {
        console.error('Error fetching units:', error);
        throw error;
    }
}

export async function getUnitStats(unitId: number): Promise<ApiResponse<{ stats: UnitStats }>> {
    try {
        const response = await axiosInstance.get<ApiResponse<{ stats: UnitStats }>>(`/admin/units/${unitId}`);
        return response.data;
    } catch (error) {
        console.error('Error fetching unit stats:', error);
        throw error;
    }
}

export async function createUnit(data: UnitData): Promise<ApiResponse<{ unit: UnitResponse }>> {
    try {
        const response = await axiosInstance.post<ApiResponse<{ unit: UnitResponse }>>('/admin/units', data);
        return response.data;
    } catch (error) {
        console.error('Error creating unit:', error);
        throw error;
    }
}

export async function updateUnit(
    unitId: number, 
    data: UnitUpdateData
): Promise<ApiResponse<{ unit: UnitResponse }>> {
    try {
        const response = await axiosInstance.put<ApiResponse<{ unit: UnitResponse }>>(`/admin/units/${unitId}`, data);
        return response.data;
    } catch (error) {
        console.error('Error updating unit:', error);
        throw error;
    }
}

export async function deleteUnit(unitId: number): Promise<ApiResponse<{ message: string }>> {
    try {
        const response = await axiosInstance.delete<ApiResponse<{ message: string }>>(`/admin/units/${unitId}`);
        return response.data;
    } catch (error) {
        console.error('Error deleting unit:', error);
        throw error;
    }
}

export async function reorderUnits(
    unitOrders: UnitOrderData
): Promise<ApiResponse<{ units: UnitResponse[] }>> {
    try {
        const response = await axiosInstance.put<ApiResponse<{ units: UnitResponse[] }>>('/admin/units/reorder', {
            units: unitOrders
        });
        return response.data;
    } catch (error) {
        console.error('Error reordering units:', error);
        throw error;
    }
}

export async function toggleUnitLock(
    unitId: number
): Promise<ApiResponse<{ unit: UnitResponse }>> {
    try {
        const response = await axiosInstance.post<ApiResponse<{ unit: UnitResponse }>>(`/admin/units/${unitId}/toggle-lock`);
        return response.data;
    } catch (error) {
        console.error('Error toggling unit lock:', error);
        throw error;
    }
}