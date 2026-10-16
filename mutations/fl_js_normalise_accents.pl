# Keep the accents: "competencia" no longer finds "Competência".
s/\.normalize\('NFD'\)\.replace\(\/\[\\u0300-\\u036f\]\/g, ''\)//;
