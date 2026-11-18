import type { RouteKey } from '../i18n/routes';

export const posts: { key: RouteKey; date: string; es: { title: string; excerpt: string }; en: { title: string; excerpt: string } }[] = [
  {
    key: 'postSeason',
    date: '2026-11-17',
    es: { title: '¿Cuál es la mejor época para visitar la Laguna San Rafael?', excerpt: 'Temporada, clima mes a mes y consejos para elegir la fecha de tu tour.' },
    en: { title: 'When is the best time to visit San Rafael Lagoon?', excerpt: 'Season, month-by-month weather and tips for choosing your tour date.' },
  },
  {
    key: 'postPack',
    date: '2026-11-17',
    es: { title: 'Qué llevar al tour de la Laguna San Rafael: lista completa', excerpt: 'Ropa, accesorios y consejos para un día de navegación en Patagonia.' },
    en: { title: 'What to pack for the San Rafael Lagoon tour', excerpt: 'Clothing, gear and tips for a day sailing in Patagonia.' },
  },
  {
    key: 'postGlacier',
    date: '2026-11-17',
    es: { title: 'Glaciar San Rafael: datos, historia y por qué visitarlo', excerpt: 'Todo sobre el glaciar del Campo de Hielo Norte y el Parque Nacional Laguna San Rafael.' },
    en: { title: 'San Rafael Glacier: facts, history and why to visit', excerpt: 'All about the Northern Patagonian Ice Field glacier and its national park.' },
  },
];
