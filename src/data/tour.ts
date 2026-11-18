/**
 * FUENTE ÚNICA DE DATOS DEL NEGOCIO Y DEL TOUR.
 * Todo el sitio (textos, JSON-LD, llms.txt) lee desde aquí, así los datos se mantienen
 * consistentes, que es clave para SEO local y GEO.
 *
 * ⚠️ Los valores marcados con TODO son provisionales: reemplázalos por los reales.
 * Los precios que se COBRAN salen de la base de datos (panel /admin); los de aquí solo se muestran.
 */
export const business = {
  name: 'Costa Carrera Travel',
  legalName: 'CC Travel', // TODO: razón social exacta
  url: 'https://travelsanrafael.cl',
  email: 'contacto@cctravel.cl',
  phones: ['+56987388886', '+56942587129'],
  phoneDisplay: ['+56 9 8738 8886', '+56 9 4258 7129'],
  whatsapp: '56987388886',
  address: {
    street: 'Dagoberto Godoy 131',
    locality: 'Puerto Río Tranquilo',
    region: 'Región de Aysén',
    country: 'CL',
  },
  geo: { lat: -46.6236, lng: -72.6747 }, // TODO: coordenadas exactas de la oficina
  hours: '08:00–18:00',
  openingDays: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
  social: {
    instagram: '', // TODO: https://instagram.com/...
    facebook: '', // TODO
    googleMaps: '', // TODO: enlace al perfil de Google Business
    tripadvisor: '', // TODO
  },
};

export const tour = {
  priceFromCLP: 75000,      // precio por persona (CLP)
  currency: 'CLP',
  durationHours: 11,        // 06:20 → llegada entre 17:00 y 18:00
  capacity: 20,
  season: { es: 'Septiembre a abril', en: 'September to April' }, // TODO: confirmar temporada
  departureTime: '06:20',
  returnTime: { es: 'entre 17:00 y 18:00 hrs', en: 'between 5:00 and 6:00 pm' },
  meetingPoint: {
    es: 'Esquina de Dagoberto Godoy con Exploradores, Puerto Río Tranquilo',
    en: 'Corner of Dagoberto Godoy and Exploradores streets, Puerto Río Tranquilo',
  },
};

export const formatCLP = (n: number) =>
  new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(n);

export const waLink = (text: string) =>
  `https://wa.me/${business.whatsapp}?text=${encodeURIComponent(text)}`;

/** Imágenes (fotos propias, optimizadas). Para cambiar una foto, reemplaza el archivo con el mismo nombre. */
type Img = { src: string; w: number; h: number; es: string; en: string };
const img = (src: string, w: number, h: number, es: string, en: string): Img => ({ src: `/images/${src}`, w, h, es, en });
export const logo = { src: '/images/logo-costa-carrera-travel.jpg', w: 200, h: 154 };
export const images: {
  hero: Img; heroMobile: Img; boat: Img; boatShore: Img; gallery: Img[];
  collage: Img[]; steps: (Img | null)[]; includes: Img; parallax: Img; reviews: Img; faq: Img; map: Img; cta: Img;
} = {
  hero: {
    src: '/images/laguna-san-rafael-brindis-frente-al-glaciar.jpg', w: 1920, h: 1080,
    es: 'Pasajeros brindan con hielo milenario frente al Glaciar San Rafael',
    en: 'Passengers toasting with ancient glacier ice in front of the San Rafael Glacier',
  },
  heroMobile: {
    src: '/images/laguna-san-rafael-brindis-frente-al-glaciar-movil.jpg', w: 900, h: 1100,
    es: '', en: '',
  },
  boat: {
    src: '/images/lancha-rapida-frente-glaciar-san-rafael.jpg', w: 1000, h: 1000,
    es: 'Lancha rápida con pasajeros frente al Glaciar San Rafael',
    en: 'Speedboat with passengers in front of the San Rafael Glacier',
  },
  gallery: [
    { src: '/images/glaciar-san-rafael-pared-de-hielo.jpg', w: 1000, h: 1000, es: 'Pared de hielo del Glaciar San Rafael', en: 'Ice wall of the San Rafael Glacier' },
    { src: '/images/icebergs-frente-glaciar-san-rafael.jpg', w: 1000, h: 1000, es: 'Témpanos frente al Glaciar San Rafael', en: 'Icebergs in front of the San Rafael Glacier' },
    { src: '/images/foca-sobre-tempano-laguna-san-rafael.jpg', w: 1000, h: 1000, es: 'Foca descansando sobre un témpano en la Laguna San Rafael', en: 'Seal resting on an ice floe in San Rafael Lagoon' },
    { src: '/images/iceberg-azul-laguna-san-rafael.jpg', w: 1000, h: 1000, es: 'Iceberg azul reflejado en la Laguna San Rafael', en: 'Blue iceberg reflected in San Rafael Lagoon' },
    { src: '/images/pasajeros-fotografiando-glaciar-san-rafael.jpg', w: 963, h: 720, es: 'Pasajeros fotografiando el glaciar desde la lancha', en: 'Passengers photographing the glacier from the boat' },
    { src: '/images/degustacion-whisky-hielo-milenario.jpg', w: 960, h: 1280, es: 'Degustación de whisky y pisco con hielo milenario a bordo', en: 'Whisky and pisco tasting with ancient glacier ice on board' },
    { src: '/images/laguna-san-rafael-foca-y-glaciar.jpg', w: 1000, h: 1000, es: 'Laguna San Rafael con el glaciar al fondo', en: 'San Rafael Lagoon with the glacier in the background' },
    { src: '/images/pasajeros-brindis-hielo-milenario.jpg', w: 1000, h: 1000, es: 'Brindis frente al glaciar en la proa de la lancha', en: 'Toast in front of the glacier on the bow of the boat' },
    { src: '/images/lancha-rapida-en-la-orilla.jpg', w: 1000, h: 1000, es: 'Nuestra lancha rápida en la orilla', en: 'Our speedboat moored at the shore' },
    { src: '/images/iceberg-detalle-laguna-san-rafael.jpg', w: 1000, h: 1000, es: 'Detalle de un iceberg en la Laguna San Rafael', en: 'Close-up of an iceberg in San Rafael Lagoon' },
    { src: '/images/foca-frente-glaciar-san-rafael.jpg', w: 1000, h: 1000, es: 'Foca sobre el hielo frente al Glaciar San Rafael', en: 'Seal on the ice in front of the San Rafael Glacier' },
    { src: '/images/frente-glaciar-san-rafael-montana.jpg', w: 1000, h: 1000, es: 'Frente del Glaciar San Rafael entre montañas', en: 'San Rafael Glacier front between mountains' },
  ],
  boatShore: img('lancha-rapida-en-la-orilla.jpg', 1000, 1000, 'Nuestra lancha rápida en la orilla', 'Our speedboat moored at the shore'),
  collage: [
    img('iceberg-vertical-laguna-san-rafael.jpg', 1000, 1679, 'Iceberg azul en la Laguna San Rafael', 'Blue iceberg in San Rafael Lagoon'),
    img('degustacion-whisky-hielo-milenario.jpg', 960, 1280, 'Whisky y pisco con hielo milenario a bordo', 'Whisky and pisco with ancient glacier ice on board'),
    img('lancha-rapida-frente-glaciar-san-rafael.jpg', 1000, 1000, 'Lancha rápida con pasajeros frente al glaciar', 'Speedboat with passengers in front of the glacier'),
  ],
  // Una foto por paso del itinerario (null = sin foto real todavía)
  steps: [
    null,
    null,
    img('icebergs-cielo-laguna-san-rafael.jpg', 988, 659, 'Navegando entre icebergs hacia la laguna', 'Sailing among icebergs toward the lagoon'),
    img('frente-glaciar-san-rafael-montana.jpg', 1000, 1000, 'Frente del Glaciar San Rafael', 'San Rafael Glacier front'),
    img('iceberg-azul-laguna-san-rafael.jpg', 1000, 1000, 'Iceberg azul en el camino de regreso', 'Blue iceberg on the way back'),
    null,
  ],
  includes: img('pasajeros-brindis-hielo-milenario.jpg', 1000, 1000, 'Brindis con hielo milenario incluido en el tour', 'Toast with ancient glacier ice, included in the tour'),
  parallax: img('glaciar-san-rafael-pared-de-hielo.jpg', 1000, 1000, 'Pared de hielo del Glaciar San Rafael', 'Ice wall of the San Rafael Glacier'),
  reviews: img('pasajeros-fotografiando-glaciar-san-rafael.jpg', 963, 720, 'Pasajeros fotografiando el glaciar', 'Passengers photographing the glacier'),
  faq: img('foca-frente-glaciar-san-rafael.jpg', 1000, 1000, 'Foca descansando frente al glaciar', 'Seal resting in front of the glacier'),
  map: img('lancha-rapida-en-la-orilla.jpg', 1000, 1000, 'Lancha lista para zarpar', 'Boat ready to sail'),
  cta: img('icebergs-cielo-laguna-san-rafael.jpg', 988, 659, 'Icebergs en la Laguna San Rafael', 'Icebergs in San Rafael Lagoon'),
};
