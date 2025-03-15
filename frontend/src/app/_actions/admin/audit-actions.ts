import { ActivityFilter, ActivityResponse } from '@/types/activity';
import axios from '@/lib/axios';

export async function getActivities(
  page: number,
  perPage: number,
  filter?: ActivityFilter
) {
  const response = await axios.post<ActivityResponse>('/api/admin/audit-logs', {
    page,
    per_page: perPage,
    start_date: filter?.date_from,
    end_date: filter?.date_to,
    action: filter?.action?.[0],
    area: filter?.category?.[0],
    user_id: filter?.user_id,
    status: filter?.impact?.[0]?.toUpperCase(),
    sort_by: 'performed_at',
    sort_order: 'desc'
  });

  return response.data;
}

export async function exportActivities(filter?: ActivityFilter) {
  const response = await axios.post('/api/admin/audit-logs/export', {
    start_date: filter?.date_from || new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString(),
    end_date: filter?.date_to || new Date().toISOString(),
    action: filter?.action?.[0],
    area: filter?.category?.[0],
    user_id: filter?.user_id,
    format: 'csv'
  }, {
    responseType: 'blob'
  });

  return response.data;
}

export async function getActivityStatistics(startDate?: string, endDate?: string) {
  const response = await axios.get('/api/admin/audit-logs/statistics', {
    params: {
      start_date: startDate,
      end_date: endDate
    }
  });

  return response.data.data;
}

export async function getUserActivity(userId: number, page = 1, perPage = 15) {
  const response = await axios.get(`/api/admin/audit-logs/users/${userId}`, {
    params: {
      page,
      per_page: perPage
    }
  });

  return response.data;
}

export async function getContentHistory(
  contentType: string,
  contentId: number,
  page = 1,
  perPage = 15
) {
  const response = await axios.get(
    `/api/admin/audit-logs/content/${contentType}/${contentId}`,
    {
      params: {
        page,
        per_page: perPage
      }
    }
  );

  return response.data;
}