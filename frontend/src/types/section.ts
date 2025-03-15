import { Question } from '@/components/admin/questions/types';
import { Quiz } from './quiz';

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

export interface PracticeContent {
  instructions: string;
  questions: Question[];
  settings: PracticeSettings;
}

export interface PracticeSettings {
  timeLimit?: number;
  maxAttempts?: number;
  showHints: boolean;
  showFeedback: boolean;
  passingScore?: number;
}

export interface MiniQuizContent {
  questions: Question[];
  settings: MiniQuizSettings;
}

export interface MiniQuizSettings {
  timeLimit?: number;
  passingScore: number;
  showExplanations: boolean;
}

export type SectionContent = TheoryContent | PracticeContent | MiniQuizContent;

export interface SectionBase {
  id: number;
  lesson_id: number;
  title: string;
  description: string;
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

// Extended Section interface that includes the quiz relationship
export interface Section extends SectionBase {
  quiz?: Quiz;
}

export interface SectionFormData extends Omit<SectionBase, 'id' | 'created_at' | 'updated_at'> {
  id?: number;
}

export const DEFAULT_SECTION_VALUES: SectionFormData = {
  lesson_id: 0,
  title: '',
  description: '',
  type: 'theory',
  order: 0,
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

// Helper type guard functions
export function isTheoryContent(content: SectionContent): content is TheoryContent {
  return 'text' in content;
}

export function isPracticeContent(content: SectionContent): content is PracticeContent {
  return 'instructions' in content;
}

export function isMiniQuizContent(content: SectionContent): content is MiniQuizContent {
  return !('text' in content) && !('instructions' in content);
}