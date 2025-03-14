'use client';

import React, { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Media } from './types';
import { useDropzone } from 'react-dropzone';
import { toast } from 'sonner';

interface MediaUploaderProps {
  media?: Media[];
  onUpdate: (media: Media[]) => void;
  allowedTypes: ('image' | 'audio')[];
  maxFiles?: number;
}

export default function MediaUploader({
  media = [],
  onUpdate,
  allowedTypes,
  maxFiles = 1,
}: MediaUploaderProps) {
  const [uploading, setUploading] = useState(false);

  const onDrop = useCallback(async (acceptedFiles: File[]) => {
    if (media.length + acceptedFiles.length > maxFiles) {
      toast.error('Error', {
        description: `Maximum ${maxFiles} files allowed`,
      });
      return;
    }

    setUploading(true);
    try {
      for (const file of acceptedFiles) {
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch('/api/admin/media/upload', {
          method: 'POST',
          body: formData,
        });

        if (!response.ok) {
          throw new Error('Upload failed');
        }

        const data = await response.json();
        onUpdate([...media, {
          id: data.id,
          type: file.type.startsWith('image/') ? 'image' : 'audio',
          url: data.url,
          alt: file.name,
        }]);
      }
    } catch (error) {
      toast.error('Error', {
        description: 'Failed to upload media',
      });
    } finally {
      setUploading(false);
    }
  }, [media, maxFiles, onUpdate]);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      ...(allowedTypes.includes('image') ? {
        'image/*': ['.jpeg', '.jpg', '.png', '.gif']
      } : {}),
      ...(allowedTypes.includes('audio') ? {
        'audio/*': ['.mp3', '.wav', '.ogg']
      } : {})
    },
    maxFiles,
  });

  const removeMedia = (index: number) => {
    const newMedia = [...media];
    newMedia.splice(index, 1);
    onUpdate(newMedia);
  };

  return (
    <div className="space-y-4">
      <div
        {...getRootProps()}
        className={`border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors ${
          isDragActive
            ? 'border-primary bg-primary/5'
            : 'border-gray-300 hover:border-primary'
        }`}
      >
        <input {...getInputProps()} />
        {isDragActive ? (
          <p>Drop the files here...</p>
        ) : (
          <div className="space-y-2">
            <p>Drag & drop files here, or click to select</p>
            <p className="text-sm text-muted-foreground">
              Allowed types: {allowedTypes.join(', ')}
            </p>
          </div>
        )}
      </div>

      {/* Preview Area */}
      {media.length > 0 && (
        <div className="grid grid-cols-2 gap-4">
          {media.map((file, index) => (
            <Card key={index} className="p-4">
              <div className="space-y-2">
                {file.type === 'image' ? (
                  <img
                    src={file.url}
                    alt={file.alt || ''}
                    className="w-full h-40 object-cover rounded"
                  />
                ) : (
                  <audio
                    controls
                    className="w-full"
                    src={file.url}
                  >
                    Your browser does not support the audio element.
                  </audio>
                )}
                <div className="flex justify-between items-center">
                  <span className="text-sm truncate max-w-[200px]">
                    {file.alt}
                  </span>
                  <Button
                    variant="outline"
                    size="sm"
                    className="text-red-600"
                    onClick={() => removeMedia(index)}
                  >
                    Remove
                  </Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      {uploading && (
        <div className="text-center py-2">
          <div className="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
          <span className="ml-2">Uploading...</span>
        </div>
      )}
    </div>
  );
}