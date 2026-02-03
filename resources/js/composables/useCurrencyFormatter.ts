// composables/useCurrencyFormatter.ts

interface EuroFormatOptions {
  locale?: string;
  minimumFractionDigits?: number;
  maximumFractionDigits?: number;
  spacing?: "normal" | "none" | "nbsp";
  
  // Opzioni Logiche
  fromCents?: boolean; // Default true (divide per 100)
  
  // ✨ NUOVE OPZIONI VISIVE
  forcePlus?: boolean; // Se true, mostra "+" per i positivi (es. "€ + 10,00")
  showSpaceAfterSign?: boolean; // Spazio tra segno e numero
}

export const useCurrencyFormatter = (globalOptions: EuroFormatOptions = {}) => {
  const baseConfig: EuroFormatOptions = {
    locale: "it-IT",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    spacing: "normal",
    fromCents: true,
    forcePlus: false,
    showSpaceAfterSign: true,
    ...globalOptions,
  };

  const format = (amount: number | null | undefined, opts: EuroFormatOptions = {}): string => {
    if (amount === undefined || amount === null) return '-';

    const config = { ...baseConfig, ...opts };

    // Calcolo valore reale
    const rawValue = config.fromCents ? amount / 100 : amount;
    const absValue = Math.abs(rawValue);

    // Formattazione numero puro
    const numberString = new Intl.NumberFormat(config.locale, {
      minimumFractionDigits: config.minimumFractionDigits,
      maximumFractionDigits: config.maximumFractionDigits,
    }).format(absValue);

    // Gestione Spazi
    const symbolSpace = config.spacing === "none" ? "" : config.spacing === "nbsp" ? "\u00A0" : " ";
    const signSpace = config.showSpaceAfterSign ? " " : "";

    // Determinazione Segno
    let sign = "";
    if (rawValue < 0) {
      sign = "-";
    } else if (rawValue > 0 && config.forcePlus) {
      sign = "+";
    }
    // Nota: se è 0, nessun segno

    // Costruzione stringa: [€] [spazio] [segno] [spazio] [numero]
    const signPart = sign ? `${sign}${signSpace}` : "";

    return `€${symbolSpace}${signPart}${numberString}`;
  };

  return {
    euro: format,
    format,
  };
};