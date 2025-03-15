'use client';

import { useState } from 'react';
import { ActivityFilter, ActivityItem, ActivityResponse } from '@/types/activity';
import { ActivityCard } from '@/components/admin/activity/ActivityCard';
import { ActivityFilter as FilterComponent } from '@/components/admin/activity/ActivityFilter';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { toast } from 'sonner';
import { ChevronLeft, ChevronRight, DownloadIcon } from 'lucide-react';

const PER_PAGE = 10;

export default function ActivityMonitorPage() {
  const [activities, setActivities] = useState<ActivityItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [filter, setFilter] = useState<ActivityFilter>({});

  const loadActivities = async (pageNum: number, currentFilter: ActivityFilter) => {
    try {
      setLoading(true);
      const response = await fetch('/api/admin/activities', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          page: pageNum,
          per_page: PER_PAGE,
          ...currentFilter,
        }),
      });
      
      if (!response.ok) {
        throw new Error(await response.text());
      }

      const data: ActivityResponse = await response.json();
      setActivities(data.items);
      setTotalPages(Math.ceil(data.total / PER_PAGE));
      setPage(pageNum);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to load activities';
      toast.error('Error', {
        description: message,
      });
      setActivities([]);
      setTotalPages(1);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (newFilter: ActivityFilter) => {
    setFilter(newFilter);
    setPage(1);
    loadActivities(1, newFilter);
  };

  const handleExport = async () => {
    try {
      const response = await fetch('/api/admin/activities/export', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(filter),
      });

      if (!response.ok) {
        throw new Error(await response.text());
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `activity-log-${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);

      toast.success('Success', {
        description: 'Activity log exported successfully',
      });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to export activity log';
      toast.error('Error', {
        description: message,
      });
    }
  };

  const handlePageChange = (newPage: number) => {
    if (newPage > 0 && newPage <= totalPages) {
      loadActivities(newPage, filter);
    }
  };

  // Initial load
  useState(() => {
    loadActivities(1, filter);
  });

  const handleViewDetails = (activity: ActivityItem) => {
    // TODO: Implement activity details modal
    console.log('View details:', activity);
  };

  return (
    <div className="p-8">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
        <div className="md:col-span-1">
          <FilterComponent onFilterChange={handleFilterChange} />
        </div>
        
        <div className="md:col-span-3 space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-2xl font-bold tracking-tight">Activity Monitor</h2>
              <p className="text-muted-foreground">
                Track and review all admin activities
              </p>
            </div>
            <Button onClick={handleExport}>
              <DownloadIcon className="h-4 w-4 mr-2" />
              Export
            </Button>
          </div>

          {loading ? (
            <Card className="p-8">
              <div className="flex items-center justify-center">
                <div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full" />
              </div>
            </Card>
          ) : activities.length === 0 ? (
            <Card className="p-8 text-center">
              <p className="text-muted-foreground">No activities found</p>
            </Card>
          ) : (
            <>
              <div className="space-y-4">
                {activities.map((activity) => (
                  <ActivityCard
                    key={activity.id}
                    activity={activity}
                    onViewDetails={handleViewDetails}
                  />
                ))}
              </div>

              <div className="flex items-center justify-between border-t pt-4">
                <Button
                  variant="ghost"
                  onClick={() => handlePageChange(page - 1)}
                  disabled={page === 1}
                >
                  <ChevronLeft className="h-4 w-4 mr-2" />
                  Previous
                </Button>
                <span className="text-sm text-muted-foreground">
                  Page {page} of {totalPages}
                </span>
                <Button
                  variant="ghost"
                  onClick={() => handlePageChange(page + 1)}
                  disabled={page === totalPages}
                >
                  Next
                  <ChevronRight className="h-4 w-4 ml-2" />
                </Button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}