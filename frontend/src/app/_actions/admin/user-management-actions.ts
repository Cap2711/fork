'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';
import { UserRole } from '@/types/user';

interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  is_active: boolean;
  last_login: string | null;
  created_at: string;
  updated_at: string;
}

interface Role {
  id: number;
  name: string;
  slug: string;
  description: string;
  permissions: Permission[];
  created_at: string;
  updated_at: string;
}

interface Permission {
  id: number;
  name: string;
  slug: string;
  description: string;
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

// User Management
export async function getUsers() {
  try {
    const response = await axiosInstance.get<ApiResponse<AdminUser[]>>('/admin/users');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch users'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getUser(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<AdminUser>>(`/admin/users/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch user'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateUser(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<AdminUser>>(`/admin/users/${id}`, {
      name: formData.get('name'),
      email: formData.get('email'),
      role: formData.get('role'),
      is_active: formData.get('is_active') === 'true'
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update user'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function toggleUserStatus(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ is_active: boolean }>>(`/admin/users/${id}/toggle-status`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to toggle user status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

// Role Management
export async function getRoles() {
  try {
    const response = await axiosInstance.get<ApiResponse<Role[]>>('/admin/roles');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch roles'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getRole(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Role>>(`/admin/roles/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch role'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function createRole(formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<Role>>('/admin/roles', {
      name: formData.get('name'),
      slug: formData.get('slug'),
      description: formData.get('description'),
      permissions: formData.getAll('permissions')
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create role'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateRole(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<Role>>(`/admin/roles/${id}`, {
      name: formData.get('name'),
      slug: formData.get('slug'),
      description: formData.get('description'),
      permissions: formData.getAll('permissions')
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update role'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteRole(id: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/roles/${id}`);
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete role'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

// Permission Management
export async function getPermissions() {
  try {
    const response = await axiosInstance.get<ApiResponse<Permission[]>>('/admin/permissions');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch permissions'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function assignRoleToUser(userId: number, roleId: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ success: boolean }>>(`/admin/users/${userId}/roles`, {
      role_id: roleId
    });
    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to assign role to user'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}

export async function removeRoleFromUser(userId: number, roleId: number) {
  try {
    const response = await axiosInstance.delete<ApiResponse<{ success: boolean }>>(`/admin/users/${userId}/roles/${roleId}`);
    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to remove role from user'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getUsersWithRole(roleId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<AdminUser[]>>(`/admin/roles/${roleId}/users`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch users with role'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}