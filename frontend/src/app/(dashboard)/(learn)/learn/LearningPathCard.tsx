import React from "react";
import Link from "next/link";
import { Card, CardContent } from "@/components/ui/card";
import { Star } from "lucide-react";
import { Progress } from "@/components/ui/progress";
import { Button } from "@/components/ui/button";

interface LearningPath {
  id: string | number;
  name: string;
  description: string;
  unlocked: boolean;
  units: number;
  unitsCompleted: number;
}

export default function LearningPathCard({
  path,
  isCompleted,
  isInProgress,
}: {
  path: LearningPath;
  isCompleted: boolean;
  isInProgress: boolean;
}) {
  const getStatusColor = () => {
    if (isCompleted) return "bg-green-100 text-green-700 border-green-200";
    if (isInProgress) return "bg-blue-100 text-blue-700 border-blue-200";
    if (path.unlocked) return "bg-amber-100 text-amber-700 border-amber-200";
    return "bg-gray-100 text-gray-500 border-gray-200";
  };

  const getStatusText = () => {
    if (isCompleted) return "Completed";
    if (isInProgress) return "In Progress";
    if (path.unlocked) return "Ready to Start";
    return "Locked";
  };

  return (
    <Card
      className={`overflow-hidden transition-all duration-300 ${
        !path.unlocked ? "opacity-70" : "hover:shadow-md"
      }`}
    >
      <div className={`px-4 py-2 font-medium ${getStatusColor()}`}>
        {getStatusText()}
      </div>
      <CardContent className="p-4">
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-lg font-bold">
            Path {path.id}: {path.name}
          </h3>
          {isCompleted && (
            <div className="flex -space-x-1">
              {[...Array(3)].map((_, i) => (
                <Star
                  key={i}
                  className="w-5 h-5 fill-amber-500 text-amber-500"
                />
              ))}
            </div>
          )}
        </div>

        <p className="mb-3 text-sm text-muted-foreground">{path.description}</p>

        <div className="mb-4">
          <div className="flex items-center justify-between mb-1 text-sm">
            <span>
              {path.unitsCompleted} / {path.units} units
            </span>
            <span>{Math.round((path.unitsCompleted / path.units) * 100)}%</span>
          </div>
          <Progress
            value={(path.unitsCompleted / path.units) * 100}
            className="h-2"
          />
        </div>

        <Link href={`/learn/path/${path.id}`}>
          <Button
            className="w-full"
            disabled={!path.unlocked}
            variant={isCompleted ? "outline" : "default"}
          >
            {isCompleted ? "Review" : isInProgress ? "Continue" : "Start"}
          </Button>
        </Link>
      </CardContent>
    </Card>
  );
}
