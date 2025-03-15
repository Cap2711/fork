import { Question } from '@/components/admin/questions/types';

export type SectionType = 'theory' | 'practice' | 'mini_quiz';

export interface MediaItem {
  type: 'image' | 'video' | 'audio';
  url: string;
  alt?: string;
}

export interface Example {
  text: string;
  explanation?: string;
}

export interface TheoryContent {
  text: string;
  media?: MediaItem[];
  examples?: Example[];
}

export interface PracticeSettings {
  timeLimit?: number;
  maxAttempts?: number;
  showHints: boolean;
  showFeedback: boolean;
  passingScore?: number;
}

export interface PracticeContent {
  instructions: string;
  questions: Question[];
  settings: PracticeSettings;
}

export interface MiniQuizSettings {
  timeLimit?: number;
  passingScore: number;
  showExplanations: boolean;
}

export interface MiniQuizContent {
  questions: Question[];
  settings: MiniQuizSettings;
}

export type SectionContent = TheoryContent | PracticeContent | MiniQuizContent;

// Base section interface as it exists in the database
export interface Section {
  id: number;
  lesson_id: number;
  title: string;
  slug: string;
  description: string | null;
  type: SectionType;
  order: number;
  content: SectionContent;
  practice_config: {
    questions?: Question[];
    instructions?: string;
    settings?: {
      timeLimit?: number;
      maxAttempts?: number;
      showHints?: boolean;
    };
  } | null;
  requires_previous: boolean;
  xp_reward: number;
  estimated_time: number;
  min_correct_required: number | null;
  allow_retry: boolean;
  show_solution: boolean;
  is_published: boolean;
  difficulty_level: string;
  created_at: string;
  updated_at: string;
}

// Form specific interface with required fields for the form
export interface SectionFormData {
  id?: number;
  lesson_id: number;
  title: string;
  description: string; // Non-nullable in the form
  type: SectionType;
  order: number;
  content: SectionContent;
  practice_config: {
    questions?: Question[];
    instructions?: string;
    settings?: {
      timeLimit?: number;
      maxAttempts?: number;
      showHints?: boolean;
    };
  } | null;
  requires_previous: boolean;
  xp_reward: number;
  estimated_time: number;
  min_correct_required: number | null;
  allow_retry: boolean;
  show_solution: boolean;
  is_published: boolean;
  difficulty_level: string;
}

export const DEFAULT_SECTION_VALUES: SectionFormData = {
  title: '',
  description: '',
  type: 'theory',
  order: 0,
  lesson_id: 0,
  content: {
    text: '',
    media: [],
    examples: [],
  } as TheoryContent,
  practice_config: null,
  requires_previous: true,
  xp_reward: 10,
  estimated_time: 5,
  min_correct_required: null,
  allow_retry: true,
  show_solution: true,
  is_published: false,
  difficulty_level: 'beginner',
};

export const DEFAULT_PRACTICE_SETTINGS: PracticeSettings = {
  showHints: true,
  showFeedback: true,
  maxAttempts: 3,
  passingScore: 70,
};

export const DEFAULT_MINI_QUIZ_SETTINGS: MiniQuizSettings = {
  passingScore: 70,
  showExplanations: true,
  timeLimit: 5,
};