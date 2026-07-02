export type CharacterData = {
    id: number;
    name: string;
    personality: string;
    status: string;
    isProcessing: boolean;
    failureReason: string | null;
    imageUrl: string | null;
    drawingUrl: string | null;
    runwayAvatarId: string | null;
};
