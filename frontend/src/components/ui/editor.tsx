'use client';

import { useEffect, useState, useCallback } from 'react';

export interface EditorProps {
  content: string;
  onChange: (content: string) => void;
}

export function Editor({ content, onChange }: EditorProps) {
  const [isMounted, setIsMounted] = useState(false);

  useEffect(() => {
    setIsMounted(true);
  }, []);

  // For now, using a simple textarea. We can enhance this with a rich text editor later
  const handleChange = useCallback(
    (e: React.ChangeEvent<HTMLTextAreaElement>) => {
      onChange(e.target.value);
    },
    [onChange]
  );

  if (!isMounted) {
    return null;
  }

  return (
    <textarea
      className="w-full min-h-[200px] px-3 py-2 rounded-md border border-input bg-background"
      value={content}
      onChange={handleChange}
      placeholder="Enter content..."
    />
  );
}