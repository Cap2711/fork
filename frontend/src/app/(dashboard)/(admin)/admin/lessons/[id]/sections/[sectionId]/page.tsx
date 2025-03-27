'use client';

import { useEffect, useState } from 'react';
import { getSection, updateSection, prepareSectionFormData } from '@/app/_actions/admin/section-actions';
import SectionForm from '@/components/admin/forms/SectionForm';
import { SectionFormData } from '@/types/section';

interface EditSectionPageProps {
  params: {
    id: string;
    sectionId: string;
  };
}

export default function EditSectionPage({ params }: EditSectionPageProps) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [section, setSection] = useState<SectionFormData | null>(null);

  useEffect(() => {
    const loadSection = async () => {
      const result = await getSection(parseInt(params.sectionId));
      if (result.error) {
        setError(result.error);
      } else if (result.data) {
        setSection(result.data as SectionFormData);
      }
      setLoading(false);
    };

    loadSection();
  }, [params.sectionId]);

  const handleSubmit = async (data: SectionFormData) => {
    const formData = prepareSectionFormData(data);
    return updateSection(parseInt(params.sectionId), formData);
  };

  if (loading) {
    return (
      <div className="flex h-[50vh] items-center justify-center">
        <div className="text-center space-y-4">
          <div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full mx-auto"></div>
          <p className="text-muted-foreground">Loading section...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex h-[50vh] items-center justify-center">
        <div className="text-center space-y-4">
          <div className="text-red-500 p-4 rounded-md bg-red-50 inline-block">
            Error: {error}
          </div>
          <button
            onClick={() => window.location.reload()}
            className="text-primary hover:underline"
          >
            Try again
          </button>
        </div>
      </div>
    );
  }

  if (!section) {
    return (
      <div className="flex h-[50vh] items-center justify-center">
        <div className="text-center space-y-4">
          <div className="text-red-500 p-4 rounded-md bg-red-50 inline-block">
            Section not found
          </div>
          <button
            onClick={() => window.history.back()}
            className="text-primary hover:underline"
          >
            Go back
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Edit Section</h2>
          <p className="text-muted-foreground">
            Update section content and settings
          </p>
        </div>
        <nav className="text-sm breadcrumbs">
          <span className="text-muted-foreground mx-2">/</span>
          <a href={`/admin/lessons/${params.id}`} className="hover:underline">
            Lesson
          </a>
          <span className="text-muted-foreground mx-2">/</span>
          <span>Edit Section</span>
        </nav>
      </div>

      <SectionForm
        lessonId={parseInt(params.id)}
        initialData={section}
        onSubmit={handleSubmit}
      />
    </div>
  );
}