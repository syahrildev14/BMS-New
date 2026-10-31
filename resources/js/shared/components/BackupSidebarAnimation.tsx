import { Lottie } from 'lottie-react';
import animationData from '@/assets/Data_visualization.json';

export default function BackupSidebarAnimation() {
  return <Lottie src={animationData} loop autoplay className="mx-auto h-36 w-36" />;
}
